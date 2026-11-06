# Windows / XAMPP deployment

## Local prerequisites

Use Windows with XAMPP PHP 8.0+ (prefer a supported PHP 8.3+ runtime for a new deployment), MySQL/MariaDB and Python 3.11+. Enable PHP extensions `pdo_mysql`, `curl`, `mbstring` and `iconv`. `php -m` lists extensions. Keep the existing project and database backups before installing an update. The application needs a PHP server and the Flask service; a static host alone cannot run it.

## Database and migration

1. Start XAMPP MySQL. Choose the existing application database when upgrading; never drop it or replace it with a sample database.
2. Back up with a restricted backup account. Use a password prompt, not a password literal in shell history. An example for local XAMPP is:

```powershell
C:\xampp\mysql\bin\mysqldump.exe -u root -p --single-transaction --routines --triggers --result-file=C:\secure-backups\career-compass-before-upgrade.sql career_guidance
```

Create the backup folder outside `htdocs`, restrict its permissions, encrypt it and test restoration on a separate database. `--result-file` avoids PowerShell redirection encoding problems. The database name and account must match your actual installation.

3. For a new installation only, create an empty database:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -p -e "CREATE DATABASE IF NOT EXISTS career_guidance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

4. Set environment variables for the CLI process and run migration:

```powershell
cd C:\xampp\htdocs\career-guidance-system
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='3306'
$env:DB_NAME='career_guidance'
$env:DB_USER='career_app'
# Set DB_PASSWORD securely in the process environment; do not paste it into shared instructions.
C:\xampp\php\php.exe scripts\migrate.php
```

A dedicated application database account is recommended. The migration account needs schema creation/alteration privileges on this one database; the serving account normally needs only SELECT, INSERT, UPDATE and DELETE. An XAMPP default root/blank account is suitable only for isolated demonstration. Production guards reject blank or placeholder passwords when `APP_ENV=production`.

The migration creates missing original/new tables, adds historical structures, imports absent directory records and widens education descriptions. It does not delete users, overwrite existing directory records or discard recommendations. Run it again safely; a changed checksum on an already applied migration deliberately stops the process. Do not edit applied migration files; add a new numbered migration.

## Python service

```powershell
py -3 -m venv .venv
.\.venv\Scripts\python.exe -m pip install -r ai_engine\requirements.txt -r tests\requirements.txt
$env:INTERNAL_API_TOKEN='your-shared-random-token'
.\.venv\Scripts\python.exe ai_engine\app.py
```

Set the same token in the PHP environment. `AI_HOST` defaults to `127.0.0.1`, `AI_PORT` to `5001`. This command is for local testing. Hosted environments use Gunicorn in Docker or another supported production WSGI server; Flask's development server is not an internet deployment server. Python's Windows Store alias may not be an installed interpreter; confirm `py -3 --version`.

## PHP development server

In another PowerShell window set the same database environment and token:

```powershell
$env:AI_API='http://127.0.0.1:5001'
C:\xampp\php\php.exe -S 127.0.0.1:8080 -t frontend
```

Open `http://localhost:8080`. Environment variables are per process; setting them in one PowerShell window does not configure another server automatically. The project-root `.env` is for Compose and is not automatically loaded by PHP.

## XAMPP Apache alternative

Configure a virtual host with `DocumentRoot "C:/xampp/htdocs/career-guidance-system/frontend"`, environment values for DB/API settings, directory access granted and `AllowOverride All`. Restart Apache after changes. Keep secrets in a restricted server configuration outside the served directory. Alternatively the traditional URL is `http://localhost/career-guidance-system/frontend/`; the root `.htaccess` blocks source/deployment/database directories when Apache honours it. Prefer a virtual host serving only `frontend/` and verify access-denial rules rather than relying on obscurity. Do not expose the project root through PHP's development server.

For email links set `APP_URL` to the URL of the PHP pages. With a subdirectory deployment it must include `/career-guidance-system/frontend`. For mandatory HTTPS use a correctly configured TLS endpoint and `APP_FORCE_HTTPS=1`; that flag sets secure cookies/HSTS and is not itself a TLS server. PHP CSP permits local assets and per-request nonce scripts only.

## Accounts and verification

Register a learner, save subject/grade data, complete the assessment and check dashboard/history. Appoint an existing operator using `scripts/security_role.php --email=... --role=admin --apply`; create a second authorised reviewer for content publication. Administrator accounts are not bundled. School/teacher assignment and learner grants are separate steps.

Mail is off until `MAIL_ENABLED=1`, a valid `MAIL_FROM`, canonical `APP_URL` and a real PHP mail transport are configured. The package does not provide SMTP credentials. Test actual delivery before `REQUIRE_EMAIL_VERIFICATION=1`. Configure `SUPPORT_EMAIL`, safeguarding and retention procedures before real learners register.

## Recovery and maintenance

Back up before migration. Because DDL auto-commits, recovery is to restore the verified backup into a separate database, check it, then switch configured application access during a maintenance window. Do not blindly restore over a live database. Preserve an account-deletion ledger outside expired backups so restoring a backup does not reactivate deleted accounts.

Run `php scripts/security_maintenance.php` to preview retention candidates; `--apply` performs documented retention cleanup. Schedule it using the operator's existing maintenance tooling. Restrict backups and logs; remove query strings from account-token access logs. Full policy and commands: `docs/SECURITY_DEPLOYMENT.md`.
