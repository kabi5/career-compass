"""Conservative matching of careers with Lesotho study/training pathways.

Listings in the bundled example catalogue are not independently verified.
Admission is checked only against explicitly versioned, reviewed rules.
"""

from datetime import date
import json
import os
import re


BASE_DIR = os.path.dirname(__file__)

PROGRAMMES_P = os.path.join(
    BASE_DIR, "data", "programmes", "programmes.json"
)

LGCSE_POINTS = {
    "A*": 1,
    "A": 2,
    "B": 3,
    "C": 4,
    "D": 5,
    "E": 6,
    "F": 7,
    "G": 8,
}

LGCSE_APPROX_PERCENT = {
    "A*": 95,
    "A": 85,
    "B": 75,
    "C": 65,
    "D": 55,
    "E": 45,
    "F": 35,
    "G": 15,
}

SUBJECT_ALIASES = {
    "math": "mathematics",
    "maths": "mathematics",
    "mathematics": "mathematics",
    "english language": "english",
    "english": "english",
    "physical sciences": "physical science",
    "physical science": "physical science",
    "physics": "physical science",
    "science": "physical science",
    "life sciences": "biology",
    "life science": "biology",
    "biology": "biology",
    "accountancy": "accounting",
    "agricultural science": "agriculture",
}


# ============================================================
# 1. LOAD PROGRAMMES AND LESOTHO EDUCATION DIRECTORY
# ============================================================

def _load():
    try:
        with open(PROGRAMMES_P, "r", encoding="utf-8") as stream:
            data = json.load(stream)

        return data if isinstance(data, list) else []

    except (FileNotFoundError, ValueError):
        return []


def _read_directory(relative_path):
    """Load original institutions, programmes and scholarships."""

    path = os.path.join(BASE_DIR, relative_path)

    if not os.path.isfile(path):
        return []

    with open(path, "r", encoding="utf-8-sig") as stream:
        return json.load(stream)


# Load original programme directory
_directory_programmes = _read_directory(
    "data/directory/programmes.json"
)

# Combine original and newer programme catalogues
PROGRAMMES = (
    _directory_programmes
    if isinstance(_directory_programmes, list)
    else []
) + _load()

# Restore compatibility with the original chatbot
INSTITUTIONS = _read_directory(
    "data/directory/institutions.json"
)

SCHOLARSHIPS = _read_directory(
    "data/directory/scholarships.json"
)


# ============================================================
# 2. NORMALISE SUBJECTS AND CAREER NAMES
# ============================================================

def canon(value):
    text = re.sub(
        r"[^a-z0-9 ]",
        " ",
        str(value or "").lower()
    )

    text = " ".join(text.split())

    return {
        "maths": "mathematics",
        "math": "mathematics",
        "physical sciences": "physical science",
        "english language": "english",
    }.get(text, text)


def subject_key(value):
    normalized = re.sub(
        r"\s+",
        " ",
        str(value or "").strip().lower()
    )

    return SUBJECT_ALIASES.get(normalized, normalized)


# ============================================================
# 3. LGCSE/COSC ACADEMIC RESULT PROCESSING
# ============================================================

def grade_to_percent(value, qualification_type="LGCSE"):
    """Convert LGCSE grades to indicative academic-fit values.

    These are approximations, not official examination marks.
    """

    if isinstance(value, bool) or value is None:
        return None

    if isinstance(value, (int, float)) and 0 <= value <= 100:
        return float(value)

    raw = str(value).strip().upper()

    if not raw:
        return None

    if re.fullmatch(r"\d+(\.\d+)?", raw):
        number = float(raw)

        return number if 0 <= number <= 100 else None

    if qualification_type == "LGCSE":
        return LGCSE_APPROX_PERCENT.get(raw)

    # COSC uses different credit and aggregate requirements.
    # Do not automatically convert COSC results to LGCSE marks.
    return None


def learner_result_map(results, marks=None):
    output = {}

    if isinstance(marks, dict):
        for field, subject in (
            ("maths", "mathematics"),
            ("science", "physical science"),
            ("english", "english"),
        ):
            value = marks.get(field)

            if (
                value is not None
                and not isinstance(value, bool)
                and isinstance(value, (int, float))
                and 0 <= value <= 100
            ):
                output[subject] = float(value)

    for item in results or []:
        if (
            isinstance(item, dict)
            and isinstance(item.get("subject"), str)
        ):
            output[subject_key(item["subject"])] = item.get("grade")

    return output


# ============================================================
# 4. VERIFY LESOTHO EDUCATION PROGRAMMES
# ============================================================

def programme_verified(programme, today=None):
    """Check evidence before treating a programme as verified."""

    if (
        not programme.get("verified")
        or not str(programme.get("source_url", "")).startswith(
            "https://"
        )
    ):
        return False

    if str(
        programme.get("accreditation_status") or ""
    ).lower() not in ("accredited", "approved"):
        return False

    try:
        reviewed = date.fromisoformat(
            programme["verified_at"]
        )

        current = today or date.today()

        if reviewed > current:
            return False

        if (current - reviewed).days > 730:
            return False

        expires = programme.get("accreditation_expires")

        if expires and date.fromisoformat(expires) < current:
            return False

    except (KeyError, TypeError, ValueError):
        return False

    return True


# ============================================================
# 5. MATCH A PROGRAMME TO A CAREER
# ============================================================

def _matches(programme, title, soc_code):
    ids = programme.get("soc_codes") or []

    if ids and soc_code in ids:
        return True

    canonical = canon(title)

    return any(
        len(canon(keyword)) >= 3
        and canon(keyword).rstrip("s") in canonical.rstrip("s")

        for keyword in programme.get("related_careers", [])
        if isinstance(keyword, str)
    )


# ============================================================
# 6. CHECK MINIMUM ACADEMIC GRADES
# ============================================================

def _passes_grade(actual, minimum, qualification_type):
    actual = str(actual).strip().upper()
    minimum = str(minimum).strip().upper()

    if qualification_type == "LGCSE":
        if (
            actual in LGCSE_POINTS
            and minimum in LGCSE_POINTS
        ):
            return (
                LGCSE_POINTS[actual]
                <= LGCSE_POINTS[minimum]
            )

        return None

    if qualification_type == "COSC":
        ranks = {
            "CREDIT": 1,
            "PASS": 2,
            "FAIL": 3,
        }

        if actual in ranks and minimum in ranks:
            return ranks[actual] <= ranks[minimum]

        return None

    return None


# ============================================================
# 7. CHECK UNIVERSITY/COLLEGE ELIGIBILITY
# ============================================================

def check_eligibility(
    programme,
    qualification_type,
    subject_results
):
    """Check documented entry requirements.

    Meeting the minimum does not guarantee admission.
    """

    unknown = {
        "status": "insufficient_information",
        "readiness_score": None,
        "gaps": [],
        "aps": None,
    }

    if not programme_verified(programme):
        return dict(unknown)

    rules = (
        programme.get("entry_rules") or {}
    ).get(qualification_type)

    if (
        not isinstance(rules, dict)
        or not isinstance(rules.get("required_grades"), dict)
        or not rules["required_grades"]
    ):
        return dict(unknown)

    result_map = {
        subject_key(k): v
        for k, v in (subject_results or {}).items()
    }

    checks = 0
    gaps = []
    incomplete = False

    total_conditions = len(rules["required_grades"])

    for subject, minimum in rules["required_grades"].items():

        actual = result_map.get(subject_key(subject))

        if actual is None:
            incomplete = True
            gaps.append(
                f"{subject}: result not supplied"
            )
            continue

        passes = _passes_grade(
            actual,
            minimum,
            qualification_type
        )

        if passes is None:
            incomplete = True
            gaps.append(
                f"{subject}: this grade format needs manual review"
            )
            continue

        checks += int(passes)

        if not passes:
            gaps.append(
                f"{subject}: {minimum} or better required "
                f"(you entered {actual})"
            )

    # Check institution-specific LGCSE admission points
    aps = None
    aps_subjects = rules.get("aps_subjects")

    if (
        qualification_type == "LGCSE"
        and "aps_max" in rules
    ):
        total_conditions += 1

        if (
            not isinstance(aps_subjects, list)
            or len(aps_subjects) != 6
        ):
            incomplete = True
            gaps.append(
                "APS must be checked against the "
                "institution's six designated subjects"
            )

        else:
            grades = [
                str(
                    result_map.get(subject_key(name), "")
                ).strip().upper()

                for name in aps_subjects
            ]

            if any(
                grade not in LGCSE_POINTS
                for grade in grades
            ):
                incomplete = True
                gaps.append(
                    "APS: six required LGCSE results "
                    "are not available"
                )

            else:
                aps = sum(
                    LGCSE_POINTS[grade]
                    for grade in grades
                )

                if aps > float(rules["aps_max"]):
                    gaps.append(
                        f"APS {aps} exceeds this "
                        f"programme's maximum {rules['aps_max']}"
                    )
                else:
                    checks += 1

    if incomplete:
        status = "insufficient_information"
        readiness = None

    else:
        status = (
            "does_not_currently_meet"
            if gaps
            else "meets_published_minimum"
        )

        readiness = round(
            100 * checks / total_conditions,
            2
        )

    return {
        "status": status,
        "readiness_score": readiness,
        "gaps": gaps,
        "aps": aps,
    }


# ============================================================
# 8. SCORE AVAILABLE EDUCATION PATHWAYS
# ============================================================

def _pathway_score(programme):
    if not programme_verified(programme):
        return None

    route = programme.get(
        "route_type",
        "direct_local"
    )

    return {
        "direct_local": 100.0,
        "alternative_local": 75.0,
        "bridging": 50.0,
        "distance": 50.0,
        "cross_border": 50.0,
    }.get(route)


# ============================================================
# 9. RECOMMEND LESOTHO PROGRAMMES FOR CAREERS
# ============================================================

def programmes_for_career(
    career_title,
    learner_subjects=None,
    limit=4,
    catalogue=None,
    *,
    soc_code="",
    qualification_type="",
    subject_results=None
):
    """Return matching programmes and available eligibility evidence.

    Retains compatibility with the original chatbot's catalogue parameter.
    """

    learner = {
        subject_key(s)
        for s in (learner_subjects or [])
    }

    grades = {
        subject_key(k): v
        for k, v in (subject_results or {}).items()
    }

    learner.update(grades.keys())

    out = []

    records = (
        PROGRAMMES
        if catalogue is None
        else catalogue
    )

    for p in records:
        if not _matches(
            p,
            career_title,
            soc_code
        ):
            continue

        required = p.get(
            "required_subjects",
            []
        )

        met = [
            s for s in required
            if subject_key(s) in learner
        ]

        missing = [
            s for s in required
            if subject_key(s) not in learner
        ]

        verified = programme_verified(p)

        eligibility = check_eligibility(
            p,
            qualification_type,
            grades
        )

        out.append({
            **p,

            "id": p.get("id"),

            "university": p.get(
                "university",
                ""
            ),

            "programme": p.get(
                "programme",
                ""
            ),

            "level": p.get(
                "level",
                ""
            ),

            "duration_years": p.get(
                "duration_years"
            ),

            "required_subjects": required,

            "missing_subjects": missing,

            "subject_fit": (
                round(
                    len(met) / len(required),
                    2
                )
                if required
                else 1.0
            ),

            "entry_requirements": p.get(
                "entry_requirements",
                ""
            ),

            "verified": verified,

            "verification_status": (
                "verified"
                if verified
                else "unverified"
            ),

            "subject_fit_note": (
                "Subject presence only; "
                "not an admission decision."
            ),

            "source_url": (
                p.get("source_url")
                if verified
                else None
            ),

            "verified_at": (
                p.get("verified_at")
                if verified
                else None
            ),

            "eligibility_status": eligibility["status"],

            "eligibility_gaps": eligibility["gaps"],

            "readiness_score": eligibility["readiness_score"],

            "aps": eligibility["aps"],

            "pathway_score": _pathway_score(p),
        })

    out.sort(
        key=lambda item: (
            item["verified"],

            item["eligibility_status"]
            == "meets_published_minimum",

            item["subject_fit"]
        ),
        reverse=True
    )

    return out[:limit]


# ============================================================
# 10. LOCAL EDUCATION AVAILABILITY SCORE
# ============================================================

def local_availability_score(programmes):
    values = [
        p.get("pathway_score")
        for p in programmes
        if p.get("verified")
    ]

    return max(
        (
            value for value in values
            if value is not None
        ),
        default=None
    )