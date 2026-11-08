"""Lesotho career exploration scoring v1.1: transparent, not a trained predictor.

The displayed percentage is a comparison of usable factors, not admission
eligibility, an employment probability, or a prediction of career success.
Only reviewed local programme rules and occupation-specific demand evidence
may contribute to the relevant scoring factors.
"""
import json
import os

import numpy as np

from riasec import DIMENSIONS, DIMENSION_NAMES, top_three_code
from programmes import grade_to_percent, learner_result_map, programmes_for_career
from labour_evidence import local_opportunity_evidence

BASE_DIR = os.path.dirname(__file__)
CAREERS_P = os.path.join(BASE_DIR, "data", "processed", "careers.json")
EDU_P = os.path.join(BASE_DIR, "data", "processed", "education.json")
SCORE_VERSION = "ls-v1.1"
WEIGHTS = {"interest": 40, "academic": 25, "subject_readiness": 15,
           "pathway": 10, "local_opportunity": 10}
MIN_DISPLAY_COVERAGE = 0.75


def _load_json(path, fallback):
    try:
        with open(path, "r", encoding="utf-8") as stream:
            return json.load(stream)
    except (OSError, ValueError):
        return fallback


CAREERS = _load_json(CAREERS_P, [])
EDUCATION = _load_json(EDU_P, {"categories": {}, "by_occupation": {}})


def _career_vector(career):
    scores = career.get("riasec_scores") or {}
    if scores:
        vector = np.array([float(scores.get(DIMENSION_NAMES[d], 0)) * 100 / 7
                           for d in DIMENSIONS], dtype=float)
        return np.clip(vector, 0, 100)
    vector = np.zeros(len(DIMENSIONS), dtype=float)
    for letter in career.get("riasec_code", ""):
        if letter in DIMENSIONS:
            vector[DIMENSIONS.index(letter)] = 100
    return vector


def _interest_fit(learner, career):
    u = np.array([float(learner.get(d, 0)) for d in DIMENSIONS], dtype=float)
    v = _career_vector(career)
    if np.linalg.norm(u) == 0 or np.linalg.norm(v) == 0:
        return None
    cosine = float(np.dot(u, v) / (np.linalg.norm(u) * np.linalg.norm(v)))
    closeness = 1.0 - float(np.abs(u - v).sum() / 600.0)
    return round(max(0, min(100, 100 * (0.70 * cosine + 0.30 * closeness))), 2)


def _subject_weights(career):
    """Provisional subject relevance. NOT published admission requirements."""
    title = career.get("title", "").lower()
    patterns = [
        (("software", "computer", "database", "cyber", "network", "programmer", "web developer"),
         {"mathematics": 0.5, "physical science": 0.3, "english": 0.2}),
        (("engineer", "electrician", "mechanic", "drafter", "architect"),
         {"mathematics": 0.5, "physical science": 0.4, "english": 0.1}),
        (("physician", "doctor", "nurs", "biolog", "pharmac", "clinical", "medical"),
         {"biology": 0.45, "physical science": 0.35, "mathematics": 0.2}),
        (("account", "audit", "financ", "budget", "econom", "statistic"),
         {"mathematics": 0.45, "accounting": 0.35, "english": 0.2}),
        (("teacher", "counsel", "social work", "writer", "journal", "public relation"),
         {"english": 0.7, "mathematics": 0.3}),
        (("agricultur", "farm", "veterinar", "animal scient", "crop"),
         {"biology": 0.5, "mathematics": 0.3, "english": 0.2}),
    ]
    for words, subject_weights in patterns:
        if any(word in title for word in words):
            return subject_weights
    code = career.get("riasec_code", "")
    if "I" in code or "R" in code:
        return {"mathematics": 0.45, "physical science": 0.35, "english": 0.2}
    if "S" in code or "A" in code:
        return {"english": 0.7, "mathematics": 0.3}
    return {"english": 0.5, "mathematics": 0.5}


def _academic_fit(career, results, qualification_type):
    total, present_weight = 0.0, 0.0
    for subject, weight in _subject_weights(career).items():
        result = grade_to_percent(results.get(subject), qualification_type)
        if result is None:
            continue
        total += result * weight
        present_weight += weight
    return round(total / present_weight, 2) if present_weight else None


def _local_opportunity_score(soc_code):
    """Backward-compatible helper returning score and source metadata."""
    finding = local_opportunity_evidence(soc_code)
    if finding["score"] is None:
        return None, None
    record = finding["evidence"][0]
    return finding["score"], {
        "source_url": record["source_url"],
        "verified_at": record["reviewed_at"],
        "methodology": record["methodology"],
        "source_occupation_mapping": record["source_occupation_mapping"],
    }


def _score_breakdown(factors):
    used = {key: value for key, value in factors.items() if value is not None}
    coverage_points = sum(WEIGHTS[key] for key in used)
    if not used:
        return None, 0.0, None, "insufficient_information"
    ranking_score = round(sum(WEIGHTS[key] * value for key, value in used.items()) /
                          coverage_points, 2)
    coverage = round(coverage_points / 100, 2)
    visible = ranking_score if coverage >= MIN_DISPLAY_COVERAGE else None
    status = ("complete" if coverage == 1 else "provisional") if visible is not None else "exploratory"
    return visible, coverage, ranking_score, status


def _infer_subjects(career):
    mapping = {"mathematics": "Mathematics", "physical science": "Physical Science",
               "english": "English", "biology": "Biology", "accounting": "Accounting"}
    return ", ".join(mapping.get(key, key.title()) for key in _subject_weights(career))


def _explanation(career, learner, factors, status, programme_list, best, coverage):
    strongest = max(DIMENSIONS, key=lambda key: learner.get(key, 0))
    parts = [
        f"Your strongest questionnaire interest is {DIMENSION_NAMES[strongest]}.",
        f"This is an exploratory comparison with the reference interest profile for {career['title']}.",
    ]
    if factors["academic"] is None:
        parts.append("Relevant marks or interpretable grades are missing, so academic preparation was not scored.")
    else:
        parts.append("Academic preparation uses available results and indicative subject relevance; it is not an admission check.")

    verified = [item for item in programme_list if item["verified"]]
    if not verified:
        parts.append("No currently verified, matching Lesotho study pathway was found in this limited catalogue.")
    elif best:
        parts.append(f"A reviewed local study route is {best['programme']} at {best['university']}.")
        if best.get("route_type") != "direct_local":
            parts.append("It is an alternative or bridging route and may not directly qualify someone for the named occupation.")

    if best and factors["subject_readiness"] is None:
        parts.append("Programme-specific eligibility cannot yet be calculated: results, qualification rules or clarified entry conditions are missing.")
    elif best and best["eligibility_status"] == "additional_review_required":
        parts.append("Submitted grades appear to satisfy the documented minimum, but additional checks such as a portfolio or interview remain.")
    elif best and best["eligibility_status"] == "does_not_currently_meet":
        parts.append("The submitted grades do not currently meet all documented entry minima for this route.")
    elif best and best["eligibility_status"] == "meets_published_minimum":
        parts.append("The supplied grades satisfy the checked published minima; this does not guarantee admission.")

    if programme_list and not verified:
        parts.append("Other listed programme suggestions are unverified; confirm them directly with the institutions.")
    if factors["local_opportunity"] is None:
        parts.append("Comparable Lesotho occupation-specific demand evidence is unavailable; national unemployment is not used as a career demand score.")
    if status == "exploratory":
        parts.append(f"Only {int(coverage * 100)}% of scoring-factor weight has usable evidence; no overall percentage is shown.")
    else:
        parts.append(f"This {status} alignment uses {int(coverage * 100)}% of scoring-factor weight, not a probability of career success.")
    parts.append("A match score does not guarantee admission, employment or career success.")
    return " ".join(parts)


def recommend(riasec_scores, marks=None, subjects=None, aspirations="", top_n=5,
              education_filter=None, min_score=0.30, qualification_type="",
              subject_results=None):
    """Return higher-coverage matches first while preserving evidence gaps."""
    del aspirations  # Do not introduce uncontrolled keyword bonuses.
    results = learner_result_map(subject_results, marks)
    subjects = list(subjects) if subjects else []
    learner_code = top_three_code(riasec_scores)
    if not learner_code:
        return []
    output = []
    for career in CAREERS:
        soc = career.get("soc_code", "")
        education = EDUCATION.get("by_occupation", {}).get(soc) or {}
        if education_filter and education and education.get("most_common_category") not in education_filter:
            continue
        interest = _interest_fit(riasec_scores, career)
        if interest is None:
            continue
        academic = _academic_fit(career, results, qualification_type)
        local_progs = programmes_for_career(
            career["title"], subjects, soc_code=soc,
            qualification_type=qualification_type, subject_results=results)
        verified_progs = [p for p in local_progs if p["verified"]]

        # Readiness, pathway and eligibility refer to the SAME reviewed route.
        # A route with known passing grades is preferred; an incomplete or
        # unverified rule never becomes an assumed pass.
        best = max(verified_progs, key=lambda p: (
            p.get("academic_minimum_met") is True,
            p["eligibility_status"] == "meets_published_minimum",
            p["readiness_score"] is not None,
            p["readiness_score"] if p["readiness_score"] is not None else -1,
            p["pathway_score"] if p["pathway_score"] is not None else -1,
        )) if verified_progs else None
        readiness = best["readiness_score"] if best else None
        pathway = best["pathway_score"] if best else None

        labour = local_opportunity_evidence(soc)
        demand = labour["score"]
        components = {
            "interest": interest, "academic": academic,
            "subject_readiness": readiness, "pathway": pathway,
            "local_opportunity": demand,
        }
        match, coverage, rank_score, score_status = _score_breakdown(components)
        if rank_score is None or rank_score < min_score * 100:
            continue

        eligibility = best["eligibility_status"] if best else "insufficient_information"
        pathway_text = (
            "; ".join(f"{p['programme']} ({p['university']}; verify details)"
                      for p in local_progs[:3])
            if local_progs else
            "Investigate local, vocational and distance-learning routes"
        )
        output.append({
            "title": career["title"], "soc_code": soc, "match_score": match,
            "ranking_score": rank_score, "data_coverage": coverage,
            "score_status": score_status, "score_version": SCORE_VERSION,
            "components": components,
            "missing_factors": [name for name, val in components.items() if val is None],
            "holland_code": career.get("riasec_code", ""),
            "explanation": _explanation(career, riasec_scores, components,
                                        score_status, local_progs, best, coverage),
            "recommended_subjects": _infer_subjects(career),
            "pathway": pathway_text,
            "education_level": education.get("most_common_label", "Not specified"),
            "eligibility_status": eligibility,
            "demand_level": labour["demand_level"] if demand is not None else "unknown",
            "description": career.get("description", ""),
            "local_programmes": local_progs,
            "evidence": [
                {"source_url": row["source_url"], "verified_at": row["reviewed_at"],
                 "methodology": row["methodology"], "occupation_classification": "SOC2018"}
                for row in labour["evidence"]
            ],
        })
    output.sort(key=lambda item: (item["data_coverage"], item["ranking_score"]), reverse=True)
    return output[:top_n]
