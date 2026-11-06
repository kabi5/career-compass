# Career Compass — Lesotho career guidance

Career Compass is a PHP/MySQL application with a private Python/Flask decision-support service. It helps secondary-school learners explore interests, compare careers, check documented academic prerequisites and build development plans. The original green, cream and peach interface and multi-page architecture are retained.

**This is a research and demonstration implementation, not a validated psychometric test, trained outcome predictor, admissions authority or employment forecasting system.** Real-school rollout still requires local content/translation review, safeguarding arrangements and operational deployment validation.

## Implemented modules

- Detailed optional profiles: subjects, LGCSE grades, percentage marks, aspirations, skills, preferences and guidance needs.
- Configurable, explained matching with separate interest alignment, academic preparation and subject/skill/preference/aspiration components; missing components are excluded and remaining weights renormalised.
- A 24-item exploratory RIASEC assessment with complete-answer checks, keyboard-friendly controls, progress, draft Sesotho questions, retained histories and comparisons.
- Profile versions and recommendation runs; recalculate after a profile change without another assessment; retain earlier recommendations and their explanations.
- Official-source institution/programme/funding directory, prerequisite checker, temporary grade/subject scenarios and up-to-three career comparison.
- Saved careers, programmes and funding opportunities; editable roadmaps, milestones and direct PDF guidance downloads.
- Recommendation-usefulness feedback and a local, source-linked retrieval assistant. External generative AI is not called by the learner chatbot.
- Teacher/counsellor portal with administrator-confirmed school assignments, explicit revocable learner grants, restricted notes and suppressed/banded aggregate CSV reports.
- Administration for users/schools, staged institution/programme/scholarship/career-reference edits, a different reviewer before publication, audit events and configuration health.
- Hashed passwords, fresh role checks, secure session controls, CSRF, persistent throttles, one-use hashed recovery/verification tokens, configurable email transport, account export/deletion and retention maintenance.
- English/Sesotho translation files, responsive forms, accessible score text/tables, reduced-motion support and an allowlisted public offline guide.

## Start with Docker Compose

1. Install/start Docker Desktop with Linux containers. Copy `.env.example` to `.env`.
2. Set three **different random secrets** for MySQL root, the application database user and `INTERNAL_API_TOKEN` (at least 32 characters). Replace all `REPLACE_WITH` placeholders. Do not put real secrets in Git or the ZIP.
3. Run `docker compose up --build -d` from the project root.
4. Visit [http://localhost:8080](http://localhost:8080). The web startup runs additive migrations and seeds missing directory records. MySQL and Flask have no published ports.
5. Register a learner, save a profile, complete the assessment and explore the new dashboard tools.

Existing deployments: **back up and test restoration before deploying**; retain the MySQL volume. Never use `docker compose down -v` for an upgrade. Detailed startup, upgrade and recovery instructions: [Docker deployment](docs/DEPLOYMENT_DOCKER.md).

## Start with Windows/XAMPP

Start XAMPP MySQL. Use PHP 8.0+ with `pdo_mysql`, `curl`, `mbstring` and `iconv`; PHP 8.3+ is preferred for a new installation. Use Python 3.11+.

```powershell
cd C:\xampp\htdocs\career-guidance-system
# Configure these for YOUR database. Local XAMPP defaults are demonstration-only.
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='3306'
$env:DB_NAME='career_guidance'
$env:DB_USER='root'
$env:DB_PASSWORD=''
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS career_guidance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
C:\xampp\php\php.exe scripts\migrate.php
py -3 -m venv .venv
.\.venv\Scripts\python.exe -m pip install -r ai_engine\requirements.txt -r tests\requirements.txt
.\.venv\Scripts\python.exe ai_engine\app.py
```

Leave Flask running. In a second PowerShell window with the same database environment:

```powershell
$env:AI_API='http://127.0.0.1:5001'
C:\xampp\php\php.exe -S 127.0.0.1:8080 -t frontend
```

Visit [http://localhost:8080](http://localhost:8080). For XAMPP Apache instead, set the environment in its virtual host and visit `/career-guidance-system/frontend/`; CLI environment values do not automatically configure an already running Apache service. See [XAMPP deployment](docs/DEPLOYMENT_XAMPP.md). PHP reads server environment variables, **not** the root `.env` file; Compose reads `.env` for interpolation.

## Administrator and educator setup

Register the operator account, then use the trusted CLI:

```powershell
php scripts/security_role.php --email=operator@example.org --role=admin
php scripts/security_role.php --email=operator@example.org --role=admin --apply
```

The first command previews the change. Create a second authorised administrator for independent content review. Administrator credentials are never seeded. An admin creates schools and assigns confirmed memberships and teacher roles. Learners then grant a specific same-school teacher access in **Account & privacy**. An entered school name alone never grants access.

## Data and methodology

The directory includes 14 institution references, 13 source-backed programme records, 13 retained unverified programme samples and three funding/enquiry records. Verification is limited to each record's `verification_scope`; accreditation and current intake are not inferred. **No bundled programme has a fully verified complete rule set.** Named prerequisites can be checked, failures explained and scenarios compared, while unresolved conditions remain undetermined. No fees, deadlines, acceptance results or labour-market forecasts are invented. See [source register](docs/SOURCES.md).

The recommendation method uses cosine interest similarity and explicit heuristic factors. Default relative weights are interest 0.60, academic 0.20, subjects 0.10, aspirations 0.05, skills 0.025, preferences 0.025. They can be configured through `RECOMMENDATION_WEIGHTS` JSON in the AI environment. Academic percentages are never converted into LGCSE grades. No outcome-labelled training set was supplied, so there is no fitted ML model or accuracy claim.

## Tests and evidence

```powershell
.\.venv\Scripts\python.exe -m pytest tests -q
Get-ChildItem frontend,scripts,tests -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Integration tests require an explicitly isolated database and running local servers; do not run against a learner database. Commands and executed results are in [QA report](docs/QA_REPORT.md). Browser screenshots use synthetic test data only.

## Documentation

- [Inspection and changes](docs/INSPECTION.md)
- [Technical implementation / Chapter 4](docs/IMPLEMENTATION.md)
- [Research protocol / Chapter 5](docs/RESEARCH_EVALUATION.md)
- [Implemented features and remaining limits](docs/FEATURES_AND_LIMITATIONS.md)
- [Security, consent, email and maintenance](docs/SECURITY_DEPLOYMENT.md)
- [Official sources and assessment licensing](docs/SOURCES.md)
- [Docker deployment](docs/DEPLOYMENT_DOCKER.md) / [Windows/XAMPP deployment](docs/DEPLOYMENT_XAMPP.md)

Email transport is not bundled and is disabled by default. Sesotho is a reviewable draft with English fallback. PDF downloads are simple paginated text reports; they are not tagged accessible PDFs. WCAG conformance, independent security review, psychometric validation and participant evaluation have not been claimed. Review the limits document before real-school deployment.
