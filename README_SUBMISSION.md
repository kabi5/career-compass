# Career Compass — Lesotho Career Guidance Prototype

**Prototype release:** 2026-11-08 · scoring model `ls-v1.1`

Career Compass is an **AI-supported, explainable career-exploration prototype** for Lesotho secondary learners. It uses a deterministic weighted recommendation algorithm with Holland/RIASEC interests, academic preparation, documented subject minima, reviewed study pathways and occupation-specific labour evidence (when available). It is **not a trained predictive model**, an admission decision, or a guarantee of employment.

## 1. What this package contains

- User registration/sign-in, learner profile with LGCSE/COSC support, and 24-item exploratory RIASEC assessment.
- PHP front end and a private Python Flask recommendation API.
- 923 occupational-reference careers, sample/curated Lesotho study pathways, and cautious source-validation rules.
- Per-factor scoring explanations, evidence coverage, eligibility caveats, a comparison tool and a subject-grade simulator.
- **Append-only** assessment and recommendation history. Retakes no longer delete previous recommendations.
- A career roadmap tracker and study/funding reference directory.
- A fresh database schema, non-destructive existing-database migration, regression tests and manual acceptance checklist.

## 2. Setup with Windows + XAMPP (existing installation)

1. **Back up** `C:\xampp\htdocs\Career_Compass` and export the live `career_guidance` database using phpMyAdmin. Do **not** import a previous full SQL backup over a live database.
2. Extract this ZIP, then copy its `Career_Compass` folder contents over your existing project, retaining a backup so you can recover local changes. This package was assembled from the uploaded October deployment ZIP plus the newer PHP files and `ls-v1.1` Python integration; **any unshared local code changes must be merged manually**.
3. Start Apache and MySQL in XAMPP. Confirm that `career_guidance` is selected.
4. From a Windows PowerShell terminal at the project root, run:

   ```powershell
   & "C:\xampp\php\php.exe" .\scripts\migrate.php
   ```

   This migrator uses `SHOW COLUMNS` and `CREATE TABLE IF NOT EXISTS`, not `information_schema`. It adds missing columns/tables and seeds the 24 assessment questions without wiping user records. If any command reports insufficient privileges, stop and inspect the exact error instead of granting broad privileges.
5. Activate your existing Python virtual environment (or create a new one) and install requirements:

   ```powershell
   cd C:\xampp\htdocs\Career_Compass
   py -3.13 -m venv .venv  # only if you do not already have a working venv
   .\.venv\Scripts\Activate.ps1
   python -m pip install -r .\ai_engine\requirements.txt
   cd .\ai_engine
   python app.py
   ```

6. In a second PowerShell terminal, test `http://127.0.0.1:5001/health` (include `X-Internal-Token` if you configured it). Check `score_version: ls-v1.1`.
7. Open **http://localhost/Career_Compass/**. The root entry point now redirects to `frontend/index.php`. Sign in and run the acceptance checklist.

**Note on environment variables:** Development defaults use MySQL `root` with an empty password and Flask bound to `127.0.0.1`. These defaults are suitable only for a local XAMPP sandbox. Before any public deployment, use a least-privilege database account, a strong internal API token, private network isolation and HTTPS; set the corresponding environment variables for both Apache/PHP and Python. The root `.env.example` is for Docker, and `ai_engine/.env.example` documents Flask's local configuration. Apache does **not** automatically load `.env` files.

## 3. Fresh database setup (new installation only)

Create a database named `career_guidance` in phpMyAdmin, then import `database/schema.sql` (this includes the six additional history/roadmap tables). Run `scripts/migrate.php` afterward to verify it and seed assessment questions idempotently.

**Do not** import `career_guidance (2).sql` from the earlier conversation to set up a clean project: that dump includes real registered-user records. This submission package intentionally contains **no personal database dump, password hashes, sessions or private API tokens**.

## 4. Verify the scoring engine

From `Career_Compass\ai_engine`:

```powershell
python -m unittest -v test_lesotho_evidence.py test_model_integration.py
```

From the project root:

```powershell
$env:PYTHONPATH = ".\ai_engine"
python -m unittest -v tests.test_recommendations tests.test_release_contract
python -m pytest tests/test_api.py  # requires Flask and pytest to be installed
```

The expected score version is `ls-v1.1`. Five factor weights are **interest 40%, academic 25%, subject readiness 15%, study pathway 10% and Lesotho labour 10%**. Unknown factors are excluded, and coverage describes the percentage of weight backed by usable evidence. A percentage only appears as a `match_score` at **75% or higher coverage**, and is still marked **provisional** until all five factors are available. Partial scores must never be described as probabilities of admission or employment.

## 5. Technical architecture

`Browser → Apache/PHP (sessions, forms, auth, CSRF) → Flask private API (RIASEC/scoring/programme checks) → MySQL (profiles, assessments, run snapshots, scoring evidence, roadmap)`.

The PHP assessment handler obtains a recommendation response *before* starting a database transaction. A successful submission inserts the assessment, individual responses, profile snapshot, run metadata, recommendation rows and full JSON evidence **in one transaction**. Errors cause rollback. It never deletes previous runs.

## 6. Important limitations

- The 24-item RIASEC activity is locally written and **not psychometrically validated**.
- Imported occupation profiles are international reference data, not a Lesotho employment or salary dataset.
- Only explicitly reviewed programmes and minimum criteria can contribute verified readiness. Some programmes require additional manual selection checks or have unresolved admission wording.
- Verified occupation-specific labour scoring records: **0** in the supplied snapshot; the 10% labour component stays unknown.
- Funding listings are reference/enquiry routes, **not live scholarship openings**.
- This package was linted and unit-tested in a Linux environment; the integrated UI/database transaction and visual behaviour still require the provided manual checks on the user's XAMPP machine.
- Student data must be treated sensitively. Obtain any necessary school/guardian permissions before research with minors. Use anonymised data in reports and screenshots.

## 7. Submission contents

Submit this source ZIP along with: a running demo or screenshots, a non-identifying sample profile and assessment, the health endpoint screenshot, automated test results, the user acceptance checklist and a separate research/dissertation report if required by your institution.

`SUBMISSION_CHECKLIST.md` contains a reproducible step-by-step test protocol. `CHANGELOG_SUBMISSION.md` explains fixes in this release.
