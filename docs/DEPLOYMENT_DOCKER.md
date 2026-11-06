# Docker Compose deployment and safe upgrades

## Fresh demonstration deployment

Start Docker Desktop's Linux container engine. Copy `.env.example` to `.env`; use three independent random values for `MYSQL_ROOT_PASSWORD`, `DB_PASSWORD` and `INTERNAL_API_TOKEN`. The internal token must have at least 32 characters. Placeholder/blank production credentials are rejected. Keep `.env` outside version control and deployment archives.

```sh
docker compose config --quiet
docker compose up --build -d
docker compose ps
docker compose logs --tail=80 web ai db
```

The website binds only to `127.0.0.1:8080` by default. Visit `http://localhost:8080`. MySQL and Flask are internal-only. MySQL initialises the original base schema; the web entrypoint runs all additive checksum-tracked migrations and missing-record seeds before starting Apache. The AI health endpoint uses the configured shared token. The Python container runs unprivileged under Gunicorn. The PHP image serves only `frontend/`; source datasets and CLI scripts are outside its document root.

Run `docker compose down` to stop. **Do not add `-v` unless intentionally destroying a disposable database volume.** No demo users, administrator passwords, real secrets or learner database dumps are included.

## Existing deployment upgrade

1. Record the current image/source revision and database name. Announce a maintenance window, stop writes and take a consistent database backup with a restricted operator account.
2. Encrypt the backup outside the served directory. Restore it into a separate database and verify account counts and a sample owned history.
3. Install the new source, retain the existing named database volume and `.env`, and build/start the new services. The web entrypoint performs additive migration; it does not replace existing users or directory edits.
4. Check logs and service health, sign in with a test account, recalculate, confirm old recommendations remain, and test teacher consent/revocation and PDF downloads.
5. If migration fails, do not edit a recorded migration checksum or delete the volume. Inspect logs, correct the cause and rerun the idempotent migration. DDL is not transactionally rolled back. If restoration is necessary, restore to a separate database and deliberately switch configuration after verification.

Manual migration command:

```sh
docker compose exec web php /var/www/scripts/migrate.php
```

For higher-assurance deployments run migrations with a dedicated operator credential, then serve with a DML-only database user and a controlled entrypoint that does not rerun migrations on every start. The supplied Compose configuration prioritises repeatable demonstration/staging setup; its web startup credential has schema privileges. Database storage should be encrypted and backups must not be accessible to the web account.

## Accounts and source publishing

Register authorised operator accounts through the application, then use the local CLI:

```sh
docker compose exec web php /var/www/scripts/security_role.php --email=operator@example.org --role=admin --apply
```

A second authorised administrator must review content drafts; an author cannot publish their own revision. Create schools and confirm memberships, assign teacher roles, then have learners opt into individual guidance access. Supplementary career records require source review and do not overwrite international interest vectors.

## Hosted deployment settings

Place a managed HTTPS reverse proxy in front of the loopback-bound web service. Configure the actual public `APP_URL`, `APP_FORCE_HTTPS=1`, a support address, trusted client-address forwarding at the web server, proxy throttles, restricted logs, monitoring, safeguarding and backup/restore drills. Do not publish MySQL or Flask ports.

`MAIL_ENABLED=0` is the default. PHP `mail()` requires an installed/configured transport or an operator-approved SMTP implementation; the image does not bundle an SMTP service. Supply a valid `MAIL_FROM`, test real delivery and only then enable required verification. There is no silent fallback that displays reset links. External generative AI remains disabled in the learner chatbot.

Optional recommendation weights use the exact keys `interest`, `academic`, `subjects`, `aspirations`, `skills`, `preferences`. Example environment value:

```dotenv
RECOMMENDATION_WEIGHTS={"interest":0.60,"academic":0.20,"subjects":0.10,"aspirations":0.05,"skills":0.025,"preferences":0.025}
```

Invalid/nonfinite/negative weights fail configuration validation. Record approved changes for research comparison; weights are included in stored run payloads.

## Verification status

The Compose configuration was checked syntactically. Docker Desktop's Linux engine was not running in the implementation environment, so image build, container startup, MySQL 8.4 execution, HTTPS proxy and mail delivery are **not claimed tested**. The full functional workflow was exercised using XAMPP PHP/MariaDB and a separate Flask process. Run the container smoke checks on the intended host before demonstrating that deployment route.
