"""Private Flask service. PHP owns identity, persistence, consent and RBAC."""
import hmac
import math
import os
from collections import Counter
from dotenv import load_dotenv
load_dotenv()
from flask import Flask, request, jsonify
from werkzeug.exceptions import HTTPException
from riasec import DIMENSIONS, score_responses, top_three_code
from model import recommend, career_match, CAREERS, METHOD_VERSION, WEIGHTS
from programmes import PROGRAMMES, INSTITUTIONS, SCHOLARSHIPS, canon
from eligibility import check_eligibility, simulate, normalise_grades, filter_scholarships, DISCLAIMER
from chatbot import BOT, CareerBot

app = Flask(__name__)
app.config['MAX_CONTENT_LENGTH'] = 512 * 1024
API_TOKEN = os.getenv('INTERNAL_API_TOKEN', '')
ASSESSMENT_VERSION = 'cc-exploratory-24-v1'
if os.getenv('APP_ENV') == 'production' and (len(API_TOKEN) < 32 or 'REPLACE_WITH' in API_TOKEN):
    raise RuntimeError('INTERNAL_API_TOKEN must contain at least 32 characters in production')

@app.before_request
def authorize():
    if API_TOKEN and not hmac.compare_digest(request.headers.get('X-Internal-Token', ''), API_TOKEN):
        return jsonify(error='Unauthorized'), 401

@app.after_request
def no_cache(response):
    response.headers['Cache-Control'] = 'no-store'
    response.headers['X-Content-Type-Options'] = 'nosniff'
    return response

@app.errorhandler(HTTPException)
def http_error(error):
    return jsonify(error=error.name), error.code


def input_json():
    value = request.get_json(silent=True)
    if not isinstance(value, dict):
        raise ValueError('Provide a JSON object')
    return value


def check_scores(scores):
    return (isinstance(scores, dict) and set(scores) == set(DIMENSIONS) and all(
        type(scores.get(k)) in (int, float) and math.isfinite(scores[k]) and 0 <= scores[k] <= 100
        for k in DIMENSIONS))


def validated_responses(responses):
    if not isinstance(responses, list) or len(responses) != 24:
        raise ValueError('The exploratory assessment requires all 24 responses')
    if any(not isinstance(r, dict) or r.get('dimension') not in DIMENSIONS or
           type(r.get('answer')) is not int or not 1 <= r['answer'] <= 5 for r in responses):
        raise ValueError('Each answer must be a whole number from 1 to 5')
    if Counter(r['dimension'] for r in responses) != Counter({d: 4 for d in DIMENSIONS}):
        raise ValueError('Provide four responses for each RIASEC dimension')
    if any('item_id' in r for r in responses):
        ids = [r.get('item_id') for r in responses]
        if any(type(i) is not int for i in ids) or len(set(ids)) != 24:
            raise ValueError('Assessment item identifiers must be unique')
    return responses


def profile_input(data, require_interest=False):
    scores = data.get('riasec_scores')
    if scores is None and 'responses' in data:
        scores = score_responses(validated_responses(data['responses']))
    if scores is not None and not check_scores(scores):
        raise ValueError('Provide all six finite RIASEC scores between 0 and 100')
    if require_interest and (scores is None or sum(scores.values()) == 0):
        raise ValueError('Complete the interest assessment before requesting personalised recommendations')
    result = {'riasec_scores': scores}
    for key in ('subjects', 'skills', 'preferences'):
        value = data.get(key, [])
        if value is None:
            value = []
        if not isinstance(value, list) or len(value) > 40 or any(not isinstance(v, str) or not 1 <= len(v.strip()) <= 100 for v in value):
            raise ValueError(key + ' must contain at most 40 non-empty text entries')
        result[key] = value
    aspirations = data.get('aspirations', '')
    if aspirations is None:
        aspirations = ''
    if not isinstance(aspirations, str) or len(aspirations) > 1000:
        raise ValueError('Aspirations must contain at most 1000 characters')
    result['aspirations'] = aspirations
    marks = data.get('marks', {})
    if marks is None:
        marks = {}
    if not isinstance(marks, dict) or len(marks) > 40:
        raise ValueError('Provide a named collection of at most 40 percentage marks')
    canonical_marks = {}
    for subject, mark in marks.items():
        if not isinstance(subject, str) or not 1 <= len(subject.strip()) <= 100:
            raise ValueError('Invalid subject name')
        if mark is not None and (type(mark) not in (int, float) or not math.isfinite(mark) or not 0 <= mark <= 100):
            raise ValueError('Percentage marks must be finite numbers between 0 and 100')
        key = canon('Physical Sciences' if canon(subject) == 'science' else subject)
        if key in canonical_marks and canonical_marks[key] != mark:
            raise ValueError('Conflicting percentage marks for ' + subject)
        canonical_marks[key] = mark
    result['marks'] = marks
    grades = data.get('subject_grades', {})
    if grades is None:
        grades = {}
    normalise_grades(grades)
    result['subject_grades'] = grades
    return result


def programme_input(data):
    programmes = data.get('programmes', PROGRAMMES)
    if not isinstance(programmes, list) or len(programmes) > 500 or any(not isinstance(p, dict) for p in programmes):
        raise ValueError('Provide at most 500 programme records from the approved catalogue')
    return programmes


@app.get('/health')
def health():
    return jsonify(status='ok', careers=len(CAREERS), programmes=len(PROGRAMMES),
                   gemini_enabled=False, method_version=METHOD_VERSION, weights=WEIGHTS)


@app.post('/score')
def score():
    try:
        data = input_json()
        scores = score_responses(validated_responses(data.get('responses')))
        return jsonify(scores=scores, top_code=top_three_code(scores), assessment_version=ASSESSMENT_VERSION,
                       interpretation='Exploratory interests only; not validated psychometric scores. Equal scores do not distinguish preferences.',
                       scale='Mean response / 5 × 100; 20 means strongly disagree and 100 strongly agree. Not a percentile.')
    except ValueError as error:
        return jsonify(error=str(error)), 400


@app.post('/recommend')
def recommend_endpoint():
    try:
        data = input_json()
        profile = profile_input(data, require_interest=True)
        count = data.get('top_n', 5)
        if type(count) is not int or not 1 <= count <= 10:
            raise ValueError('Result count must be a whole number from 1 to 10')
        all_results = recommend(**profile, top_n=count + 3, programme_catalogue=programme_input(data))
        results, alternatives = all_results[:count], all_results[count:count+3]
        return jsonify(riasec_scores=profile['riasec_scores'], top_code=top_three_code(profile['riasec_scores']),
                       recommendations=results, alternative_suggestions=alternatives,
                       assessment_version=ASSESSMENT_VERSION, method_version=METHOD_VERSION, weights=WEIGHTS,
                       methodology='Transparent weighted matching with missing components excluded; not a trained predictive model.')
    except (ValueError, TypeError) as error:
        return jsonify(error=str(error)), 400


@app.post('/compare')
def compare():
    try:
        data = input_json()
        titles = data.get('career_titles')
        if not isinstance(titles, list) or not 1 <= len(titles) <= 3 or any(not isinstance(t, str) or len(t) > 160 for t in titles):
            raise ValueError('Choose one to three career titles')
        if len(set(titles)) != len(titles):
            raise ValueError('Choose different careers to compare')
        profile = profile_input(data)
        lookup = {c['title']: c for c in CAREERS}
        return jsonify(careers=[career_match(lookup[t], **profile, programme_catalogue=programme_input(data)) for t in titles if t in lookup],
                       unknown_titles=[t for t in titles if t not in lookup], method_version=METHOD_VERSION)
    except (ValueError, TypeError) as error:
        return jsonify(error=str(error)), 400


@app.post('/eligibility')
def eligibility_endpoint():
    try:
        data = input_json()
        return jsonify(results=check_eligibility(programme_input(data), data.get('subject_grades', {}), data.get('grading_system', 'LGCSE')),
                       disclaimer=DISCLAIMER)
    except (ValueError, TypeError) as error:
        return jsonify(error=str(error)), 400


@app.post('/simulate')
def simulate_endpoint():
    try:
        data = input_json()
        return jsonify(simulate(programme_input(data), data.get('baseline_grades', {}),
                                data.get('scenario_grades', {}), data.get('grading_system', 'LGCSE')))
    except (ValueError, TypeError) as error:
        return jsonify(error=str(error)), 400


@app.get('/careers')
def careers():
    query = request.args.get('q', '').strip().lower()[:80]
    try:
        page = max(1, min(int(request.args.get('page', '1')), 100))
        per = max(1, min(int(request.args.get('per_page', '12')), 30))
    except ValueError:
        return jsonify(error='Invalid pagination'), 400
    matching = sorted((c for c in CAREERS if query in c['title'].lower()), key=lambda c: c['title'])
    start = (page - 1) * per
    return jsonify(total=len(matching), page=page, items=[{'title': c['title'], 'riasec_code': c.get('riasec_code', '')}
                                                       for c in matching[start:start + per]])


@app.get('/programmes')
def programmes():
    university = request.args.get('university', '').lower()[:100]
    items = [p for p in PROGRAMMES if not university or university in p.get('university', '').lower()]
    return jsonify(count=len(items), programmes=items)


@app.get('/scholarships')
def scholarships():
    items = filter_scholarships(SCHOLARSHIPS, request.args.get('q', '')[:100],
                               request.args.get('level', '')[:100], request.args.get('status', '')[:20])
    return jsonify(count=len(items), scholarships=items)


@app.post('/chat')
def chat():
    try:
        data = input_json()
        message = data.get('message')
        context = data.get('context', {})
        if context is None:
            context = {}
        if not isinstance(message, str) or not 1 <= len(message.strip()) <= 500 or not isinstance(context, dict):
            raise ValueError('Message must be 1–500 characters')
        code = context.get('riasec_code', '')
        subjects = context.get('subjects', [])
        if not isinstance(code, str) or len(code) > 6 or any(c not in DIMENSIONS for c in code):
            raise ValueError('Invalid interest context')
        if not isinstance(subjects, list) or len(subjects) > 40 or any(not isinstance(s, str) or len(s) > 100 for s in subjects):
            raise ValueError('Invalid subject context')
        # Whitelist context. No identity, marks or notes reach the assistant.
        catalogues = {}
        for name in ('programmes', 'institutions', 'scholarships'):
            if name in data:
                records = data[name]
                if not isinstance(records, list) or len(records) > 500 or any(not isinstance(r, dict) for r in records):
                    raise ValueError('Invalid approved ' + name + ' catalogue')
                catalogues[name] = records
        bot = CareerBot(**catalogues) if catalogues else BOT
        return jsonify(bot.reply(message, {'riasec_code': code, 'subjects': subjects}))
    except (ValueError, TypeError) as error:
        return jsonify(error=str(error)), 400


if __name__ == '__main__':
    app.run(host=os.getenv('AI_HOST', '127.0.0.1'), port=int(os.getenv('AI_PORT', '5001')), debug=False)
