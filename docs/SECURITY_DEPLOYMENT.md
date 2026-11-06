# Security, educator access and operator procedures

This describes implemented behaviour, not a claim of a security audit or legal compliance. Complete the operator's safeguarding and privacy review before enrolling real learners.

## Authentication and account recovery

- PHP password hashing uses `PASSWORD_DEFAULT`; passwords are 12–72 bytes to prevent bcrypt truncation. Login regenerates the session identifier and CSRF token. Cookie flags are HttpOnly, SameSite=Lax and Secure under HTTPS.
- Roles are reloaded from MySQL on each request. Sessions expire after 30 idle minutes or eight hours total. Password reset and role/school changes increment `user_security.auth_version`, invalidating earlier sessions.
- All state-changing forms require a session-bound random CSRF token. IP-based database throttling survives deleted cookies. Login and recovery additionally throttle a hashed account key. Reverse-proxy throttling should complement this mechanism; a school using one public IP may require an operator-reviewed limit change.
- Forwarded headers are never trusted for rate-limit client identity. Configure the trusted reverse proxy to supply a correct server remote address; do not trust arbitrary `X-Forwarded-For` values.
- Reset and verification tokens use 32 cryptographically random bytes, store SHA-256 hashes only, expire (reset 30 minutes, verification 24 hours), are purpose-bound and single-use. Tokens are consumed in a transaction with row locking. A reset invalidates earlier sessions. Token URLs redirect to a clean URL before showing page content and use no-referrer.
- Recovery request responses do not reveal whether the account exists. The application never displays emailed tokens or writes them to public logs. Configure web-server access logs to omit query strings on recovery/verification URLs, because the initial requested URL contains the token. Disable third-party analytics on all account pages.

Email delivery is **disabled unless configured**. Set:

```dotenv
MAIL_ENABLED=0
MAIL_FROM=career-compass@your-operator-domain.example
APP_URL=https://your-host.example/career-compass
REQUIRE_EMAIL_VERIFICATION=0
SUPPORT_EMAIL=operator@your-operator-domain.example
```

`APP_URL` must be the public base URL of the PHP pages (for XAMPP, include `/career-guidance-system/frontend`; for the Docker web root, no `/frontend`). `MAIL_ENABLED=1` uses PHP's native `mail()` transport. This requires a separately configured local MTA or authenticated, encrypted SMTP relay managed by the server. The package has no bundled mail service or SMTP credentials. On Windows configure PHP/sendmail appropriately; on Docker install/configure an approved mail transport in the web image or replace the transport with an approved mail library. Do not enable mandatory verification until a real test message is delivered successfully. `MAIL_ENABLED` and syntax-valid settings show "configured", which is not proof of delivery. No actual external email was sent in automated tests.

For a production deployment set a long shared `INTERNAL_API_TOKEN`, `APP_FORCE_HTTPS=1` behind the configured HTTPS reverse proxy, and optionally a separate random `RATE_LIMIT_SECRET`. The latter defaults to the internal token; the local development fallback is not appropriate for hosting. PHP private pages have `Cache-Control: no-store`; CSP blocks third-party scripts, framing, objects and unsafe script URLs. Inline scripts require the generated nonce. Unexpected web exceptions return a reference code rather than SQL, paths or credentials.

## Administrative bootstrap and role changes

Self-registration always creates a learner. The web administration interface can assign a learner or educator role to another non-admin account. It cannot appoint administrators or change the acting administrator's role. All role/school changes require the administrator's current password and revoke prior educator grants and sessions.

After migration, register the operator account normally. From a trusted local shell with the configured database environment:

```powershell
C:\xampp\php\php.exe scripts\security_role.php --email=operator@example.org --role=admin
C:\xampp\php\php.exe scripts\security_role.php --email=operator@example.org --role=admin --apply
```

Docker equivalent: `docker compose exec web php /var/www/scripts/security_role.php --email=operator@example.org --role=admin --apply`. The first command is a dry run; the tool refuses to demote the last administrator. It changes only an existing account's role and creates no predictable password. Appoint a second authorised reviewer to use the content-publication workflow. Restrict OS/SSH access to these tools. Scripts refuse HTTP invocation and must remain outside the public document root.

## Controlled educator access

1. An administrator creates a school code and confirms users' school memberships in Administration. The original free-text `users.school` field is never an authorisation boundary.
2. A learner explicitly grants a particular same-school educator access in Account & privacy. The acknowledgement records a versioned consent event; it is not independently verified guardian consent.
3. Every individual-record request checks educator role, the active grant and matching non-null `user_security.school_id`. No administrator bypass is provided. A teacher sees only their own notes for that learner in the current school.
4. Revocation, role demotion or school reassignment removes access. Role/school changes revoke all affected grants and associated consent events. A new grant is required after reassignment.

Aggregates include each learner's latest assessment only. Groups below five are suppressed and other counts are banded in fives; CSV applies the same rules and escapes spreadsheet formulas. This reduces disclosure risks but is not a formal differential-privacy guarantee. Educators should not combine reports with other data to identify learners. Counselling notes must stay brief and limited to guidance, agreed actions and follow-up dates. Learners can export notes about them.

## Content verification

The admin catalogue covers institutions, programmes, scholarships and supplementary career records. Editing stages a JSON draft in `content_submissions`. An administrator cannot approve their own draft. Another administrator must inspect the official source, confirm verification scope and encoded rules, enter review notes, and reauthenticate before publication. Rejected drafts do not change published records. Publication updates programme admission-rule mirrors and the catalogue in one transaction, with an audit event.

The interface validates HTTPS URLs, source metadata, dates, field types, programme references, LGCSE rule syntax and scholarship date order. These checks cannot prove that a webpage is authoritative or accurately interpreted; that is the reviewers' documented responsibility. Verification scope must distinguish existence, prerequisites, completeness, accreditation and current availability. Unknown publication years/deadlines remain null; partial rule sets must remain `complete:false`. Never declare a programme complete based only on named subject minima when aggregate qualifications, points or interviews are unresolved.

Use the checked-in JSON records in `ai_engine/data/directory/` as schemas. Programme rules support `subject`, `any_subject`, `count`, and `manual`. Verified publication is a content workflow, not institutional endorsement. Retire inaccurate information through a reviewed correction with unresolved conditions marked; this edition does not provide a bulk destructive content-delete action. Operator publication changes are read by PHP directories and passed to relevant API requests; seed JSON updates remain a controlled deployment step.

## Retention, deletion and backups

Learners can export personal JSON and permanently delete their active account after password confirmation and typing `DELETE`. Cascading foreign keys remove learner profiles, assessments, recommendation histories, plans, saved records and notes about the learner. Administrative audit actors are set null on user deletion; account target IDs are removed. Administrative self-deletion is blocked until the role is transferred. Backups and web logs are outside this operation and require the operator's retention process.

Maintenance defaults to 365 days for counselling notes and audit entries, seven days after token expiry, and two days for rate-limit buckets:

```powershell
C:\xampp\php\php.exe scripts\security_maintenance.php
C:\xampp\php\php.exe scripts\security_maintenance.php --apply
```

The default is a dry run. `COUNSELLING_RETENTION_DAYS` and `AUDIT_RETENTION_DAYS` accept 30–3650. Review policy before applying, then schedule the approved command daily in Windows Task Scheduler or the deployment scheduler. No scheduler is installed by this package. This script does not purge active account or assessment history. If the operator needs a maximum learner-record retention period, implement an approved inactivity/departure process with notice and access/export support; do not silently erase research records.

Use a dedicated runtime database account with only `SELECT, INSERT, UPDATE, DELETE` on the application database. Run migrations with a separate temporary account allowed `CREATE, ALTER, INDEX, REFERENCES`; revoke DDL rights from the runtime account afterwards. Docker's default auto-created app user has broad rights on its own database and must be narrowed for public deployment. Do not expose database/AI ports publicly.

Before migration, use `mysqldump --single-transaction --routines --triggers` for MySQL or the matching `mariadb-dump` client and a restricted operator credential file, not a password embedded in a command. Save the dump outside the web root with restrictive file permissions and encrypted storage. Record a checksum, test restoration into a separate named database, and compare user/assessment counts and representative records. Restore never overwrites the production database until the operator has approved a recovery window and verified the backup. Reapply the additive migration on the restored copy. Maintain an external deletion ledger so restored backups do not reactivate deleted accounts. Define and publish backup retention, store encryption keys separately, and test a recovery at least before deployment and after schema changes.

## Executed checks and remaining limits

On 2026-10-27, XAMPP PHP 8.0.30 and a disposable local MariaDB database on port 3307 executed:

- `tests/security_test.php`: **36 passed** — consent, cross-school denial, revoked permission, role reload, session expiry/invalidation, persistent throttles, password limits, random/single-use/expired/purpose-bound tokens, token hashing, email-verification state, CSV formulas, source metadata and malformed inputs.
- `tests/security_http_test.php`: **26 passed** — actual HTTP logins, CSRF rejection, anonymous/learner role denial, consent/revocation, teacher notes, JSON export, separate-reviewer publication, generic recovery response and suppressed aggregate CSV.
- Security PHP files passed syntax checks. Maintenance dry-run completed without deleting records.

Run the tests only against a migrated database named `security_test_*`. For HTTP tests start the PHP built-in server on a loopback port with the same database environment, set `SECURITY_TEST_URL=http://127.0.0.1:8093`, and run the HTTP script. Synthetic fixtures are removed in `finally`; the disposable database and de-identified audit records can remain for inspection. Tests do not use real learner records.

Not performed: external mail delivery, production TLS/reverse-proxy validation, penetration testing, independent privacy review, guardian-consent verification, backup encryption/restore exercise on operator infrastructure or concurrency/load testing. Server access log token redaction, maintenance scheduling, email transport and school identity checks remain deployment responsibilities.
