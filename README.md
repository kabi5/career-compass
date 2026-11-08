# Career Compass — Career Guidance System for Lesotho

## Lesotho five-factor scoring update (November 2026)

This package includes the **ls-v1.0 exploratory matching engine**. It uses the following proposed, **not yet validated**, weights when all factors have credible data:

| Factor | Weight |
| --- | ---: |
| RIASEC interest alignment | 40% |
| Academic preparation | 25% |
| Verified subject-entry readiness | 15% |
| Verified study/training pathways | 10% |
| Sourced occupation-level opportunities | 10% |

The **24-item questionnaire is required** (four answers per RIASEC category). All 'strongly disagree' answers now yield 0 rather than 20 in that dimension. The learner profile accepts structured `Subject: Grade` lines and distinguishes LGCSE/COSC. The bundled local programme entries remain unverified, and `ai_engine/data/labour/opportunities.json` is intentionally empty. Consequently, a typical learner receives **exploratory career suggestions without a fabricated overall percentage** until sufficient verified evidence is available. Grade midpoints are indicative preparation signals, never official admission decisions. The dashboard and career-detail page reflect this distinction.

**Existing XAMPP/MySQL deployments must be backed up, then migrated before serving the new PHP files:** `C:\xampp\php\php.exe scripts\migrate.php` from the project root. Do not reimport `schema.sql` over an existing database. The migration adds the qualification and score-evidence fields, allows an unknown numeric match and retains earlier data. Fresh installations import `database/schema.sql` once.

Run the engine checks with `python -m unittest discover -s tests -p test_recommendations.py -v`. For complete endpoint tests, install the project dependencies and `pytest`, then run `python -m pytest tests -q`. Before deploying, confirm that PHP has cURL, PDO MySQL and mbstring, that MySQL is accessible and that the Flask service can respond to `/recommend`. See `docs/LESOTHO_SCORING_IMPLEMENTATION.md` for the data schema and release checklist.

**Source policy:** No institution, programme, entry rule, accreditation or employment outlook is certified by the sample files. Review and document official sources and validity dates before enabling those scoring factors. The current 40/25/15/10/10 weights and 24 questionnaire items require expert review, usability testing and evaluation before being described as scientifically validated.

A redesigned, responsive, multi-page career guidance website built on the original **PHP + MySQL + Python/Flask** project. It helps secondary-school learners explore RIASEC interests, receive transparent career suggestions, inspect possible Lesotho study routes, and ask a question-answering assistant. This is an **educational decision-support prototype**, not a validated psychometric assessment, a certified admission service, or a live labour-market forecasting system.

## What has been upgraded

- Fully redesigned landing page, navigation, dashboard, profile, career assessment, career detail, career browser, sign-in, registration and assistant.
- Accessible responsive design (green, cream, peach), keyboard focus states, mobile menu and small-screen layouts; no external design framework required.
- 24 exploratory RIASEC assessment questions added to initial database setup.
- Fixed the original mismatch: PHP tried inserting `holland_code`, `education_level`, and `local_programmes` into database columns that did not exist.
- Safer password requirements, credential hashing, prepared SQL, session renewal at login, CSRF validation on state-changing actions, simple per-session throttling, safer error handling, and private AI API token support.
- Transparent matching with reference career-interest vectors and academic factors. Removed unsupported local job-demand claims. Approximate matching **is not the same as a trained ML predictor**.
- Reference career browser, optional retrieval-based chat; generative AI is **disabled by default** for learner privacy.
- A Docker Compose development/staging deployment configuration, XAMPP instructions, unit tests and a non-destructive database migration script.

## Recommended option — Docker Compose (Windows / Linux)

**Prerequisites:** Docker Desktop running, with Docker Compose available. Production should use a secure HTTPS reverse proxy.

1. Extract this archive to a new project folder. Open a terminal in that folder (the one containing `docker-compose.yml`).
2. Copy `.env.example` to `.env`. Fill in **three different long random secrets** for `MYSQL_ROOT_PASSWORD`, `DB_PASSWORD` and `INTERNAL_API_TOKEN`. For example, generate a 64-character token from a trusted password manager. **Do not use the example placeholder values and never publish `.env`.**
3. Run:

   ```bash
   docker compose up --build -d
   ```
4. Wait for services to become healthy (`docker compose ps`). Visit **http://localhost:8080**.
5. Register a learner account, fill in a profile, complete the assessment, and check the recommendations, details and chatbot.
6. To see logs use `docker compose logs --tail=100 web ai db`.
7. To stop: `docker compose down`. **Never use `docker compose down -v` unless you intend to delete the database.**

For a remote server, put an HTTPS reverse proxy in front of the web container. The compose file binds the website to **127.0.0.1:8080** intentionally, so it is not exposed to the internet directly. The AI engine and MySQL are internal-only services. Configure TLS, set `APP_FORCE_HTTPS=1` when behind a correctly configured HTTPS reverse proxy, backup schedules, monitoring, IP/firewall policy, school consent requirements, an operator privacy/contact policy and a tested restore procedure before public launch. Run the app only on infrastructure that allows PHP/MySQL and the Python service; a static hosting provider alone will not run it.

**Existing deployments**: First back up MySQL. Deploy the new code, keep the existing MySQL database, and run `php scripts/migrate.php` in the *web runtime* with DB environment variables present. This adds the three missing recommendation fields if absent and safely seeds the questions. It does not remove accounts or recommendations. On Docker:

```bash
docker compose exec -T web php -r 'echo "PHP connection test: "; $p=new PDO("mysql:host=".getenv("DB_HOST").";dbname=".getenv("DB_NAME"),getenv("DB_USER"),getenv("DB_PASSWORD")); echo "OK\n";'
```

For an existing Docker database, run `docker compose exec web php /var/www/scripts/migrate.php` **after backing up your database**. For XAMPP, run `php scripts/migrate.php` from the project root with the correct database environment. The migration does not delete user records.

## Alternative — XAMPP on Windows 10

1. Extract to **`C:\xampp\htdocs\career-guidance-system`**.
2. Start **Apache** and **MySQL** from XAMPP.
3. Open phpMyAdmin, choose **Import**, and import `database/schema.sql`. This creates the `career_guidance` database and seeds assessment questions. If the database already exists from an older version, **back it up** and run the non-destructive migration via PHP CLI.
4. Configure PHP environment variables for database access, or set them in Apache's virtual-host configuration:
   - `DB_HOST=127.0.0.1`
   - `DB_NAME=career_guidance`
   - `DB_USER=root` (local development only)
   - `DB_PASSWORD=` (only if using XAMPP default local MySQL)
   - `AI_API=http://127.0.0.1:5001`
   - `INTERNAL_API_TOKEN=` (for local-only testing; use a shared secret for hosted deployments)
5. Install Python 3.11 or later and create a fresh virtual environment:

   ```powershell
   cd C:\xampp\htdocs\career-guidance-system\ai_engine
   py -3 -m venv .venv
   .\.venv\Scripts\Activate.ps1
   python -m pip install -r requirements.txt
   python app.py
   ```

6. In your browser open **`http://localhost/career-guidance-system/frontend/`**. Keep the Python window open while testing the site.
7. For local updates, run `C:\xampp\php\php.exe scripts\migrate.php` from your project root after confirming your DB settings. If you used environment configuration rather than default credentials, those variables must be set in that terminal too.

**Important for hosting:** XAMPP default passwords, PHP development settings and Flask's single-process server are for local testing only. Do not publish them directly to the internet. The Docker AI image runs Gunicorn instead of Flask's development server.

## Running checks

```bash
python -m pip install -r ai_engine/requirements.txt
python -m pytest tests -q
```

PHP syntax lint: `php -l frontend/index.php` or run it on all PHP files. For a complete smoke test, the MySQL database and Flask service must both be running.

## Where things live

| Location | Purpose |
| --- | --- |
| `frontend/` | PHP web pages |
| `frontend/config/db.php` | Sessions, security helpers, database, internal API wrapper |
| `frontend/config/layout.php` | Consistent site header/footer |
| `frontend/assets/css/style.css` | Responsive visual design |
| `frontend/assets/js/app.js` | Assessment progress and mobile menu |
| `ai_engine/app.py` | Flask endpoints |
| `ai_engine/model.py` | Transparent interest/academic matching |
| `ai_engine/data/processed/` | International career-interest reference dataset |
| `ai_engine/data/programmes/programmes.json` | Sample Lesotho tertiary programme listings; not independently verified |
| `database/schema.sql` | Initial schema and 24 assessment questions |
| `scripts/migrate.php` | Non-destructive old-schema upgrade |
| `tests/` | Unit/API tests |

## Known limitations and public-launch checklist

1. **Data validity:** The included career dataset is not a Lesotho employment-demand dataset. A high match means similar expressed interests, not a 95% chance of career success. Research validation and fairness evaluation remain necessary.
2. **Local educational information:** The supplied local programmes dataset has `verified: false` entries. Verify current programmes, duration, entry requirements and funding information against official institution prospectuses **before** treating them as authoritative. There is no automated update feed.
3. **Learners may be minors:** The organisation operating this service must establish guardian/school consent as appropriate, retention/deletion policies, access-rights processes, and privacy compliance before real pupils submit information. The in-app privacy explanation is not a substitute for an operator-specific legally reviewed policy.
4. **Accounts:** Authentication is implemented, but self-service password reset and email verification are **not implemented**. Deploying to a real school requires a supported secure account-recovery process and administrative support.
5. **Abuse protection:** Per-session throttling is only a basic safeguard. Public deployment requires proxy-level rate limiting, monitoring and bot protection.
6. **Administrator controls:** `admin.php` contains a protected aggregate dashboard only. It is not a full editor for career information. Content updates are performed through the checked-in reference files. No one can self-register as admin. Grant admin access only through a trusted database administrator.
7. **Infrastructure:** This package is packaged for deployment, but must be deployed and tested on the target environment with functioning MySQL, Flask, HTTPS, backups and real school user testing before being called production-ready.
8. **Secrets:** The original uploaded ZIP included an `ai_engine/.env` file, which this improved release deliberately **excludes**. If it contained an API key and the original archive was shared, rotate that key. Do not upload `.env` or `.git` to public storage.

*Original project source is preserved in spirit and expanded; no claim of completed academic evaluation, verified job-demand predictions, participant testing or production rollout is made.*
