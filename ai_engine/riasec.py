"""Holland RIASEC scoring utilities."""

DIMENSIONS = ["R", "I", "A", "S", "E", "C"]

DIMENSION_NAMES = {
    "R": "Realistic",
    "I": "Investigative",
    "A": "Artistic",
    "S": "Social",
    "E": "Enterprising",
    "C": "Conventional",
}


def score_responses(responses):
    """
    responses: list of dicts [{"dimension":"I","answer":4}, ...]
    answer on a 1-5 Likert scale.
    Returns dict of dimension -> normalised score (0-100).
    """
    totals = {d: 0 for d in DIMENSIONS}
    counts = {d: 0 for d in DIMENSIONS}

    for r in responses:
        d = r.get("dimension")
        if d in totals:
            totals[d] += int(r.get("answer", 0))
            counts[d] += 1

    scores = {}
    for d in DIMENSIONS:
        max_possible = counts[d] * 5 if counts[d] else 1
        scores[d] = round((totals[d] / max_possible) * 100, 2) if counts[d] else 0.0

    return scores


def top_three_code(scores):
    """Return 3-letter RIASEC code ordered by descending score."""
    ordered = sorted(scores.items(), key=lambda kv: kv[1], reverse=True)
    return "".join([d for d, _ in ordered[:3]])