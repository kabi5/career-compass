# Lesotho scoring implementation notes

**Algorithm:** `ls-v1.0` (exploratory, unvalidated)  
**Date:** 8 November 2026

## Included files

The updated source includes `ai_engine/riasec.py`, `model.py`, `programmes.py`, `app.py`, `frontend/profile.php`, `frontend/assessment.php` and `database/schema.sql`. To keep the application usable, it also updates `frontend/dashboard.php`, `frontend/career-detail.php`, `scripts/migrate.php`, the tests and this documentation. Previous uploaded deployment files are not overwritten.

## Deployment order

1. Back up the existing `career_guidance` database and the running application folder.
2. Stop requests to the application while migrating. From the **new** project root, run `php scripts/migrate.php` with the normal database environment variables. For fresh installs only, import `database/schema.sql`.
3. Deploy the new Python engine files and supporting data. Install dependencies listed in `ai_engine/requirements.txt` and start the Flask/Gunicorn service.
4. Deploy the new PHP files (including `dashboard.php` and `career-detail.php`). Check PHP extensions `curl`, `mbstring`, `pdo_mysql`.
5. Test `/health`, register a disposable learner, save a profile, answer 24 questions, submit, inspect the dashboard and verify explanation/coverage/eligibility labels. Never destroy or reset real learner data to test.
6. Run `python -m unittest discover -s tests -p test_recommendations.py -v`, and with Flask and pytest installed run `python -m pytest tests -q`.

## Present response contract

`POST /recommend` accepts:

```json
{
  "responses": [
    {"question_id": 1, "dimension": "R", "answer": 4}
  ],
  "marks": {"maths": 73, "science": 68, "english": 82},
  "subjects": ["Mathematics", "English", "Physical Science"],
  "qualification_type": "LGCSE",
  "subject_results": [
    {"subject": "Mathematics", "grade": "B"},
    {"subject": "English", "grade": "C"}
  ],
  "top_n": 5
}
```

**The example `responses` array shows the shape only. In reality it must contain all 24 entries, exactly four per dimension.** Additional optional fields are `aspirations` (up to 1000 characters). Results preserve the original grade, and LGCSE letter-grade midpoints are only used for exploratory academic fit. COSC credit/pass values are not converted to LGCSE percentages.

Each recommendation includes `components`, `match_score` (possibly `null`), `ranking_score`, `data_coverage` (fraction), `score_status` (`complete`, `provisional`, or `exploratory`), `eligibility_status`, `missing_factors`, `local_programmes`, `evidence` and `score_version`. A conditional score is displayed only when the available weighted evidence is at least 75%; exploratory low-coverage scores remain hidden although the engine has an internal sorting value. Different evidence-coverage groups rank separately.

## Curating real programme entries

The shipped `ai_engine/data/programmes/programmes.json` contains sample entries with `verified: false`. Leave them false until checked by a responsible person against official materials. This is a **schema illustration, not a claim that any example institution/programme meets these requirements:**

```json
{
  "id": "example-computing",
  "university": "Example Provider",
  "programme": "Example Computing Programme",
  "level": "Degree",
  "related_careers": ["software developer"],
  "soc_codes": [],
  "required_subjects": ["Mathematics", "English"],
  "route_type": "direct_local",
  "verified": false,
  "source_url": "https://example.invalid/prospectus",
  "verified_at": "2026-11-08",
  "accreditation_status": "unknown",
  "accreditation_expires": null,
  "entry_rules": {
    "LGCSE": {
      "required_grades": {"Mathematics": "B", "English": "C"},
      "aps_max": 26,
      "aps_subjects": ["Mathematics", "English", "Biology", "Sesotho", "History", "Geography"]
    },
    "COSC": {"required_grades": {"Mathematics": "CREDIT", "English": "PASS"}}
  }
}
```

For the actual score to activate, the record must have `verified: true`, an HTTPS source, an approved/accredited status, a review date within 730 days, and an accreditation expiry that has not passed. Programme eligibility is still limited to machine-readable documented rules, not acceptance or a guaranteed place. LGCSE APS computation requires **six explicitly named subjects** and applies only when the individual programme's verified rule specifies it. A rule for NUL cannot be assumed to apply to another institution.

For higher education, consult the Lesotho Council on Higher Education's live accreditation service at https://lqmis.che.ac.ls/ and the specific institution's current prospectus. For technical/vocational training, use the responsible official TVET source. Never mark the record verified from a search snippet or unreviewed advertisement.

## Curating local opportunity evidence

The engine loads `ai_engine/data/labour/opportunities.json`, intentionally containing `[]`. A **future** curated item may use:

```json
{
  "soc_code": "EXAMPLE-CODE",
  "country_code": "LS",
  "score": 71,
  "verified": false,
  "occupation_specific": true,
  "source_url": "https://example.invalid/verified-report",
  "verified_at": "2026-11-08",
  "methodology": "Documented method, sample size, comparison group and observation window"
}
```

The engine ignores records that are not explicitly verified or do not identify Lesotho, occupation specificity, date, source and methodology. The value must represent a defensible index on 0–100 **based on comparable evidence**; it is not a raw vacancy count or a general national unemployment figure. Never fabricate it simply to fill the 10% demand factor.

## Security, data quality and limitations

- The 24 questions have not been validated as a clinical or occupational aptitude test. This is an exploratory educational tool.
- Grading schemes, annual admission criteria and accreditation status can change. Review by year and institution before enabling automated checks.
- The placeholder career-to-subject relevance weights are heuristics. Teacher/counsellor review is essential before research claims or public launch.
- The current international occupation vectors are not Lesotho employment statistics.
- The original database and earlier assessment records are retained. New recommendations carry `ls-v1.0` in the score version.
- A blank career match percentage is intentional when evidence is inadequate; the dashboard must show **Exploratory**, not `0%`.
- A learner's district, disability, gender, finances or background must not lower an aptitude score. Use preferences only to find accessible pathways or support.
