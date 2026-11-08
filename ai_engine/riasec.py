"""Transparent scoring for the 24 exploratory Holland/RIASEC questions.

Four items per dimension, answers 1..5. This is not a validated psychometric
instrument and the scores are not ability, admission or employment predictions.
"""

DIMENSIONS = ["R", "I", "A", "S", "E", "C"]
DIMENSION_NAMES = {
    "R": "Realistic", "I": "Investigative", "A": "Artistic",
    "S": "Social", "E": "Enterprising", "C": "Conventional",
}
ITEMS_PER_DIMENSION = 4
TOTAL_ITEMS = len(DIMENSIONS) * ITEMS_PER_DIMENSION


def validate_responses(responses):
    """Reject incomplete, duplicated or malformed 24-question submissions."""
    if not isinstance(responses, list) or len(responses) != TOTAL_ITEMS:
        raise ValueError(f"Exactly {TOTAL_ITEMS} assessment answers are required.")
    counts = {d: 0 for d in DIMENSIONS}
    seen_ids = set()
    for item in responses:
        if not isinstance(item, dict):
            raise ValueError("Each answer must be an object.")
        dimension, answer = item.get("dimension"), item.get("answer")
        if dimension not in counts or type(answer) is not int or not 1 <= answer <= 5:
            raise ValueError("Each answer needs a valid RIASEC dimension and a value 1–5.")
        counts[dimension] += 1
        if "question_id" in item:
            identifier = item["question_id"]
            if type(identifier) is not int or identifier < 1 or identifier in seen_ids:
                raise ValueError("Assessment question IDs must be unique positive integers.")
            seen_ids.add(identifier)
    if any(value != ITEMS_PER_DIMENSION for value in counts.values()):
        raise ValueError("Each RIASEC category requires exactly four answers.")
    return True


def score_responses(responses):
    """Return 0..100 per dimension; all '1' answers yield 0, all '5' yield 100."""
    validate_responses(responses)
    totals = {d: 0 for d in DIMENSIONS}
    for item in responses:
        totals[item["dimension"]] += item["answer"] - 1
    possible = ITEMS_PER_DIMENSION * 4
    return {d: round(100 * totals[d] / possible, 2) for d in DIMENSIONS}


def top_three_code(scores):
    """Top-three letter code, or empty when all dimensions are zero."""
    if not isinstance(scores, dict) or max((float(scores.get(d, 0)) for d in DIMENSIONS), default=0) <= 0:
        return ""
    ordered = sorted(DIMENSIONS, key=lambda d: (-float(scores.get(d, 0)), DIMENSIONS.index(d)))
    return "".join(ordered[:3])
