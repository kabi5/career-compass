# Executed quality checks — 27 October 2026

Tests used synthetic accounts in a separate local MariaDB 10.4 server on `127.0.0.1:3307`. The existing application database was not used or modified. PHP 8.0.30 served the application on port 8088, Flask on port 5002; a second PHP server on 8093 used a separate security test database. These are development servers, not a production deployment.

| Check | Executed result |
|---|---|
| Python unit/Flask API tests | 109 passed |
| PHP syntax | 45 files passed |
| Learner HTTP workflows | 126 passed; `workflow-results.json` records every assertion |
| Security/authentication/access-control checks | 36 passed |
| HTTP authentication, CSRF, consent and content publication | 26 passed |
| Legacy database migration, preservation, idempotency and foreign keys | 9 passed |
| Docker Compose configuration parsing | Passed with temporary placeholder test secrets |
| Desktop/mobile browser inspection | Completed; observations below |
| PDF export | Actual two-page A4 PDF parsed and both pages rendered/visually inspected |

Python coverage includes missing marks, weight renormalisation, strict assessment completeness, malformed/non-finite input, conflicting subject aliases, unknown requirements, count/manual admission rules, temporary scenarios, closed/expired funding, retrieval sources and live catalogue updates. Tests do not establish psychometric validity or real-world recommendation accuracy.

Learner workflows cover registration, privilege-injection rejection, profile validation, assessment, recommendation details/feedback, profile recalculation with retained history, eligibility explanations, unchanged real grades after simulation, comparison, saves, editable plans/milestones, PDF, funding status, chatbot sources and privacy. The integration run exposed and fixed a real legacy field-length defect: long qualification descriptions now use TEXT. A separate regression checks that operator-edited catalogue content is preserved on migration reruns.

Security tests exercise cross-school restrictions even with an erroneous grant, immediate consent revocation and role changes, owned record access, CSRF, generic recovery responses, single-use tokens, role safeguards, independent content review and CSV safety. Passing these tests is not an external penetration-test certification.

## Browser and export inspection

- Inspected dashboard, profile, assessment, directory, comparison, roadmap, history, simulator, saved progress and account pages at desktop/mobile sizes. `browser-results.json` records measured document/client widths and missing input labels.
- Checked 390 px and 320 px mobile viewport settings. A header overflow at 320 px was found and fixed; rechecks showed matching content/client width. Tables use contained scrolling where appropriate.
- Used the keyboard to select an assessment radio answer and confirmed the progress announcement changed. Checked mobile menu expansion and Escape dismissal.
- Saved synthetic screenshots in `docs/previews/upgraded-*.png`. No real learner information was used.
- PDF output has no JavaScript or form fields; standard-font A4 text is paginated and readable. Poppler emitted local missing fallback-font warnings but rendered both pages. Exports are not tagged accessible PDFs.
- Public offline caching is restricted in code to four static assets. Authenticated PHP responses have `Cache-Control: no-store`. A complete offline install/device/network-throttling trial was not performed.

## Reproduce safely

Install the application and test requirements in an isolated Python environment. Run `python -m pytest tests -q`. For PHP syntax, use `Get-ChildItem frontend,scripts,tests -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }`.

For database tests, point `DB_HOST`, `DB_PORT`, `DB_USER` and `DB_PASSWORD` at a **disposable test server**. Never supply production credentials. Set `DB_NAME=security_test_career_compass`, create that database, and run `php scripts/migrate.php`. Start PHP with those same environment settings on `127.0.0.1:8093`. Set `SECURITY_TEST_URL=http://127.0.0.1:8093`, then run `php tests/security_test.php` and `php tests/security_http_test.php`. They create and remove their own fixtures.

For migration preservation, set `ALLOW_DISPOSABLE_DB_TESTS=1` and run `php tests/migration_test.php` against that isolated server. It creates a randomly named `migration_test_*` database, imports the archived schema, checks preservation and repeated migration, then drops only that generated database.

For learner workflows, create/migrate `career_compass_qa`, start PHP on 8088 and Flask on 5002 with `AI_API=http://127.0.0.1:5002` in the PHP environment. Set `CC_TEST_BASE_URL=http://127.0.0.1:8088` and `CC_TEST_DB_NAME=career_compass_qa`, then run `python tests/workflow_http.py`. Synthetic records remain in that disposable database. Temporary browser credentials and the test PDF are written to the OS temporary directory, outside the deployment archive. Repeated rapid runs can correctly trigger application throttling; wait for its window rather than disabling protection.

## Unexecuted checks and limitations

The Docker CLI was available but its daemon connection failed (Docker Desktop engine named pipe unavailable). Image builds, container startup and MySQL 8.4 runtime behaviour were therefore **not executed**. Configuration parsing alone does not prove deployment success. Actual XAMPP Apache virtual-host configuration was not exercised; PHP's local server and MariaDB were used for the functional checks.

No email relay, delivery credentials, public TLS proxy, production database or backup destination was supplied. Actual mail delivery, remote deployment, production restore drills, concurrent-load testing, external security review, full screen-reader/contrast auditing and WCAG conformance were not completed. No participants were recruited and no usability, psychometric reliability, career relevance or predictive accuracy statistics were fabricated. The Chapter 5 document is a protocol for future evaluation.

## Deployment archive

Run `python scripts/package.py` to create `dist/Career_Compass_Upgraded_YYYY-MM-DD.zip` and its SHA-256 sidecar. The script includes source, data, migrations, tests, documentation and synthetic screenshots; excludes virtual environments, caches, logs, keys and live `.env` files; and verifies every archived file against `PACKAGE_MANIFEST.json` plus ZIP CRC checks. The original uploaded archive is left unchanged.
