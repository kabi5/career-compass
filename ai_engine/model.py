"""Explainable weighted matching, not trained ML or an outcome predictor.
Occupation profiles are international references. Subject, skills and preference
links are exploratory heuristics, never admission rules. Missing data is omitted.
"""
import json
import math
import os
import re
from pathlib import Path
from riasec import DIMENSIONS, DIMENSION_NAMES
from programmes import programmes_for_career, canon

BASE_DIR = Path(__file__).parent
METHOD_VERSION = 'weighted-exploration-2.0'
CAREERS = json.loads((BASE_DIR / 'data/processed/careers.json').read_text(encoding='utf-8'))
EDUCATION = json.loads((BASE_DIR / 'data/processed/education.json').read_text(encoding='utf-8'))
DEFAULT_WEIGHTS = {'interest': .60, 'academic': .20, 'subjects': .10,
                   'aspirations': .05, 'skills': .025, 'preferences': .025}


def configured_weights(overrides=None):
    raw = overrides if overrides is not None else json.loads(os.getenv('RECOMMENDATION_WEIGHTS', '{}'))
    if not isinstance(raw, dict) or set(raw) - set(DEFAULT_WEIGHTS):
        raise ValueError('RECOMMENDATION_WEIGHTS contains unrecognised components')
    weights = {**DEFAULT_WEIGHTS, **raw}
    if any(type(v) not in (int, float) or not math.isfinite(v) or v < 0 for v in weights.values()):
        raise ValueError('Weights must be finite non-negative numbers')
    if sum(weights.values()) <= 0:
        raise ValueError('At least one recommendation weight must be positive')
    return weights


WEIGHTS = configured_weights()
DIMENSION_SUBJECTS = {
    'R': ['Mathematics', 'Physical Sciences'],
    'I': ['Mathematics', 'Physical Sciences', 'Biology'],
    'A': ['English', 'Art'], 'S': ['English', 'Biology'],
    'E': ['Business Studies', 'Mathematics'], 'C': ['Mathematics', 'Accounting'],
}
DIMENSION_SKILLS = {
    'R': ['practical work', 'repairing', 'building'],
    'I': ['problem solving', 'research', 'analysis'],
    'A': ['creativity', 'design', 'writing'],
    'S': ['communication', 'teaching', 'listening'],
    'E': ['leadership', 'persuasion', 'planning'],
    'C': ['organisation', 'accuracy', 'record keeping'],
}
DIMENSION_PREFERENCES = {
    'R': ['outdoors', 'hands on', 'practical'],
    'I': ['research', 'investigation', 'solving problems'],
    'A': ['creative work', 'independence', 'creativity'],
    'S': ['helping people', 'teamwork', 'teaching'],
    'E': ['leadership', 'business', 'entrepreneurship'],
    'C': ['structured work', 'organisation', 'data'],
}


def _career_vector(code, scores=None):
    if scores:
        vector = [max(0, float(scores.get(DIMENSION_NAMES[d], 0))) for d in DIMENSIONS]
        if sum(vector):
            return vector
    return [1.0 if d in (code or '') else 0.0 for d in DIMENSIONS]


def _cosine(left, right):
    norm = math.sqrt(sum(x*x for x in left) * sum(x*x for x in right))
    return sum(a*b for a, b in zip(left, right)) / norm if norm else None


def _subjects(career):
    return list(dict.fromkeys(s for d in career.get('riasec_code', '')
                             for s in DIMENSION_SUBJECTS.get(d, [])))


def _infer_subjects(career):
    return ', '.join(_subjects(career))


def _academic_details(career, marks):
    # The legacy percentage field is called science. This alias is deliberately
    # confined to an exploratory indicator, never to admission-rule matching.
    normalised = {canon('Physical Sciences' if canon(k) == 'science' else k): v for k, v in (marks or {}).items()}
    supplied, missing = [], []
    for subject in _subjects(career):
        value = normalised.get(canon(subject))
        if type(value) in (int, float) and math.isfinite(value) and 0 <= value <= 100:
            supplied.append({'subject': subject, 'percentage': value})
        else:
            missing.append(subject)
    score = sum(v['percentage'] for v in supplied) / len(supplied) if supplied else None
    return score, supplied, missing


def _academic_fit(career, marks, subjects=None):
    score, _, _ = _academic_details(career, marks)
    return score / 100 if score is not None else None


def _heuristic_alignment(values, code, vocabulary):
    if not values:
        return None, []
    matched = {d for d, terms in vocabulary.items()
               if any(canon(value) in {canon(t) for t in terms} for value in values)}
    if not matched:
        return None, []
    return 100 * len(matched.intersection(code)) / len(matched), sorted(matched)


def _aspiration_alignment(aspirations, title):
    if not aspirations.strip():
        return None
    words = {w.rstrip('s') for w in re.findall(r'[a-z]+', aspirations.lower()) if len(w) > 3}
    title_words = {w.rstrip('s') for w in re.findall(r'[a-z]+', title.lower()) if len(w) > 3}
    return 100 * len(words.intersection(title_words)) / max(1, len(title_words))


def career_match(career, riasec_scores=None, marks=None, subjects=None,
                 aspirations='', skills=None, preferences=None, subject_grades=None,
                 programme_catalogue=None):
    code = career.get('riasec_code', '')
    learner_vector = [float((riasec_scores or {}).get(d, 0)) for d in DIMENSIONS]
    similarity = _cosine(learner_vector, _career_vector(code, career.get('riasec_scores')))
    interest = 100 * similarity if similarity is not None else None
    academic, used_marks, absent_marks = _academic_details(career, marks)
    subject_list = list(dict.fromkeys((subjects or []) + list((subject_grades or {}).keys())))
    learner_subjects = {canon(s) for s in subject_list}
    suggested = _subjects(career)
    present = [s for s in suggested if canon(s) in learner_subjects]
    absent = [s for s in suggested if canon(s) not in learner_subjects]
    compatibility = 100 * len(present) / len(suggested) if learner_subjects and suggested else None
    skill_score, skill_dimensions = _heuristic_alignment(skills, code, DIMENSION_SKILLS)
    preference_score, preference_dimensions = _heuristic_alignment(preferences, code, DIMENSION_PREFERENCES)
    components = {'interest': interest, 'academic': academic, 'subjects': compatibility,
                  'aspirations': _aspiration_alignment(aspirations, career['title']),
                  'skills': skill_score, 'preferences': preference_score}
    denominator = sum(WEIGHTS[k] for k, v in components.items() if v is not None)
    effective = {k: round(WEIGHTS[k] / denominator, 6) if denominator and v is not None else 0
                 for k, v in components.items()}
    match = (sum(WEIGHTS[k]*v for k, v in components.items() if v is not None) / denominator
             if denominator else None)
    education = EDUCATION.get('by_occupation', {}).get(career.get('soc_code', '')) or {}
    local = programmes_for_career(career['title'], subject_list, catalogue=programme_catalogue)
    missing = [name for name, score in components.items() if score is None]
    explanation = []
    if interest is not None:
        explanation.append(f'Interest alignment is {interest:.1f}/100 by cosine similarity between your six interest scores and the reference occupation profile.')
        if len(set(learner_vector)) == 1:
            explanation.append('Your interest scores are equal, so the assessment does not yet distinguish preferred activities.')
    else:
        explanation.append('Interest alignment is unavailable; complete an assessment to add this component.')
    if academic is not None:
        explanation.append(f'Academic preparation is {academic:.1f}/100: the mean of {len(used_marks)} supplied percentage mark(s) in suggested subjects.')
    else:
        explanation.append('No relevant percentage marks were supplied; the academic component is excluded, not estimated. Letter grades are reserved for admission-rule checks.')
    explanation.append('Subject, skill and preference links are exploratory RIASEC heuristics; aspiration alignment uses title-keyword overlap.')
    if missing:
        explanation.append('Unavailable components (' + ', '.join(missing) + ') are excluded and the remaining weights are renormalised.')
    explanation.append('This is career exploration, not admission eligibility, a probability of success, a trained ML prediction or a measure of Lesotho job demand.')
    skills_suggested = list(dict.fromkeys(s for d in code for s in DIMENSION_SKILLS.get(d, [])))
    activities = [DIMENSION_NAMES[d] + ' activities' for d in code if d in DIMENSION_NAMES]
    return {
        'title': career['title'], 'soc_code': career.get('soc_code', ''),
        'match_score': _rounded(match), 'holland_code': code,
        'interest_alignment': _rounded(interest), 'academic_preparation': _rounded(academic),
        'subject_compatibility': _rounded(compatibility), 'aspiration_alignment': _rounded(components['aspirations']),
        'skills_alignment': _rounded(skill_score), 'preference_alignment': _rounded(preference_score),
        'component_scores': {k: _rounded(v) for k, v in components.items()},
        'configured_weights': WEIGHTS, 'effective_weights': effective,
        'missing_information': missing, 'academic_evidence': used_marks,
        'academic_missing_subjects': absent_marks, 'subject_matches': present,
        'suggested_subjects_not_listed': absent,
        'skills_matched_dimensions': skill_dimensions, 'preferences_matched_dimensions': preference_dimensions,
        'explanation': ' '.join(explanation), 'recommended_subjects': _infer_subjects(career),
        'pathway': _pathway_for(education, local),
        'education_level': education.get('most_common_label', 'Not specified in reference data'),
        'education_source': 'International occupation reference; not a Lesotho admission requirement',
        'demand_level': 'unknown',
        'labour_market_information': 'No verified local labour-market evidence is available in this catalogue.',
        'description': career.get('description', ''), 'local_programmes': local,
        'skills': skills_suggested, 'typical_activities': activities,
        'activity_source': 'Exploration prompts inferred from RIASEC; not occupation-specific task evidence',
        'method_version': METHOD_VERSION,
    }


def _rounded(value):
    return round(value, 2) if value is not None else None


def recommend(riasec_scores, marks, subjects, aspirations='', top_n=5,
              education_filter=None, min_score=.30, skills=None, preferences=None,
              subject_grades=None, programme_catalogue=None):
    results = []
    for career in CAREERS:
        education = EDUCATION.get('by_occupation', {}).get(career.get('soc_code'))
        if education_filter and (not education or education.get('most_common_category') not in education_filter):
            continue
        result = career_match(career, riasec_scores, marks, subjects, aspirations,
                              skills, preferences, subject_grades, programme_catalogue)
        if result['match_score'] is not None and result['match_score'] >= min_score*100:
            results.append(result)
    results.sort(key=lambda r: (-r['match_score'], r['title']))
    return results[:top_n]


def _pathway_for(education, local_programmes):
    if local_programmes:
        return '; '.join(f"{p['programme']} ({p['university']}; {p.get('verification_status', 'unverified')})" for p in local_programmes[:3])
    return education.get('most_common_label', 'Discuss relevant qualifications with a counsellor')
