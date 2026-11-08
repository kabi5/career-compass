
"""Private Flask service used by the PHP application, not a public-facing API."""
import os
from dotenv import load_dotenv

load_dotenv()
from flask import Flask, request, jsonify
from riasec import DIMENSIONS, score_responses, top_three_code, validate_responses
from model import recommend, CAREERS, SCORE_VERSION, WEIGHTS, MIN_DISPLAY_COVERAGE
from programmes import PROGRAMMES, programme_verified
from labour_evidence import load_evidence
from chatbot import BOT
import gemini_client

app = Flask(__name__)
app.config["MAX_CONTENT_LENGTH"] = 40 * 1024
API_TOKEN = os.getenv("INTERNAL_API_TOKEN", "")
if os.getenv("APP_ENV") == "production" and not API_TOKEN:
    raise RuntimeError("INTERNAL_API_TOKEN must be set in production")


@app.before_request
def authorize():
    # The microservice still needs private network isolation and a shared token.
    if API_TOKEN and request.headers.get("X-Internal-Token") != API_TOKEN:
        return jsonify({"error": "Unauthorized"}), 401


def input_json():
    obj = request.get_json(silent=True)
    return obj if isinstance(obj, dict) else None


def check_scores(scores):
    return (
        isinstance(scores, dict) and
        all(isinstance(scores.get(k), (int, float)) and
            not isinstance(scores.get(k), bool) and
            0 <= scores[k] <= 100 for k in DIMENSIONS)
    )


def input_profile(data):
    """Validate optional school context; keep LGCSE and COSC distinct."""
    marks = data.get("marks") or {}
    subjects = data.get("subjects") or []
    aspirations = data.get("aspirations") or ""
    qualification = data.get("qualification_type") or ""
    results = data.get("subject_results") or []
    if (not isinstance(marks, dict) or not isinstance(subjects, list)
            or not isinstance(aspirations, str) or not isinstance(results, list)):
        raise ValueError("Invalid academic profile")
    if qualification not in ("", "LGCSE", "COSC", "OTHER"):
        raise ValueError("Unknown qualification type")
    if (len(subjects) > 40 or len(results) > 40 or len(aspirations) > 1000
            or any(not isinstance(s, str) or len(s) > 100 for s in subjects)):
        raise ValueError("Academic profile is too long")
    for key in ("maths", "science", "english"):
        value = marks.get(key)
        if value is not None and (type(value) not in (int, float) or not 0 <= value <= 100):
            raise ValueError("Marks must be numbers from 0 to 100")
    for item in results:
        if not isinstance(item, dict) or not isinstance(item.get("subject"), str):
            raise ValueError("Invalid subject result")
        subject, grade = item["subject"].strip(), item.get("grade")
        if (not 1 <= len(subject) <= 100 or not isinstance(grade, (str, int, float))
                or isinstance(grade, bool)):
            raise ValueError("Invalid subject or grade")
        if not 1 <= len(str(grade).strip()) <= 20:
            raise ValueError("Invalid grade length")
    return marks, subjects, aspirations, qualification, results


@app.get("/health")
def health():
    records = load_evidence().get("occupation_specific_entries", [])
    count = len(records) if isinstance(records, list) else 0
    return jsonify(
        status="ok", careers=len(CAREERS), programmes=len(PROGRAMMES),
        verified_programmes=sum(programme_verified(p) for p in PROGRAMMES),
        occupation_labour_records=count,
        gemini_enabled=gemini_client.is_configured(),
        score_version=SCORE_VERSION, factor_weights=WEIGHTS,
        min_display_coverage=MIN_DISPLAY_COVERAGE,
    )


@app.post("/score")
def score():
    data = input_json()
    if data is None or not isinstance(data.get("responses"), list):
        return jsonify(error="Invalid assessment responses"), 400
    try:
        validate_responses(data["responses"])
    except ValueError as error:
        return jsonify(error=str(error)), 400
    scores = score_responses(data["responses"])
    return jsonify(scores=scores, top_code=top_three_code(scores))


@app.post("/recommend")
def recommend_endpoint():
    data = input_json()
    if data is None:
        return jsonify(error="Invalid JSON input"), 400
    scores = data.get("riasec_scores")
    if scores is None:
        responses = data.get("responses")
        try:
            validate_responses(responses)
        except (ValueError, TypeError) as error:
            return jsonify(error=str(error)), 400
        scores = score_responses(responses)
    if not check_scores(scores):
        return jsonify(error="Invalid interest scores"), 400
    try:
        marks, subjects, aspirations, qualification, results = input_profile(data)
    except ValueError as error:
        return jsonify(error=str(error)), 400
    try:
        n = int(data.get("top_n", 5))
    except (ValueError, TypeError):
        return jsonify(error="Invalid result count"), 400
    if n < 1 or n > 10:
        return jsonify(error="Result count must be between 1 and 10"), 400
    results = recommend(
        riasec_scores=scores, marks=marks, subjects=subjects,
        aspirations=aspirations, top_n=n, qualification_type=qualification,
        subject_results=results,
    )
    return jsonify(
        riasec_scores=scores, top_code=top_three_code(scores),
        recommendations=results,
        score_version=SCORE_VERSION, factor_weights=WEIGHTS,
        min_display_coverage=MIN_DISPLAY_COVERAGE,
        methodology=(
            "Exploratory Lesotho five-factor matching. Verified local pathways "
            "use reviewed current programme and admission data; labour demand "
            "is scored only with audited occupation-specific evidence. "
            "Missing factors are omitted and evidence coverage is disclosed. "
            "This is not a trained predictor or an admission decision."
        ),
    )


@app.get("/careers")
def careers():
    q = (request.args.get("q", "") or "").strip().lower()[:80]
    try:
        page = max(1, min(int(request.args.get("page", "1")), 100))
        per = max(1, min(int(request.args.get("per_page", "12")), 30))
    except ValueError:
        return jsonify(error="Invalid pagination"), 400
    matching = [c for c in CAREERS if q in c["title"].lower()]
    matching.sort(key=lambda c: c["title"])
    start = (page - 1) * per
    return jsonify(total=len(matching), page=page, items=[
        {"title": c["title"], "riasec_code": c.get("riasec_code", "")}
        for c in matching[start:start + per]
    ])


@app.get("/programmes")
def programmes():
    university = (request.args.get("university", "") or "").lower()[:100]
    items = []
    for p in PROGRAMMES:
        if university and university not in p.get("university", "").lower():
            continue
        verified = programme_verified(p)
        items.append({**p, "verified": verified,
                      "verification_status": "verified" if verified else "unverified"})
    return jsonify(count=len(items), programmes=items)


@app.post("/chat")
def chat():
    data = input_json()
    if data is None:
        return jsonify(error="Invalid JSON"), 400
    msg, context = data.get("message"), data.get("context") or {}
    if (not isinstance(msg, str) or not 1 <= len(msg.strip()) <= 500
            or not isinstance(context, dict)):
        return jsonify(error="Message must be 1–500 characters"), 400
    return jsonify(BOT.reply(msg, context))


if __name__ == "__main__":
    app.run(host=os.getenv("AI_HOST", "127.0.0.1"),
            port=int(os.getenv("AI_PORT", "5001")), debug=False)



