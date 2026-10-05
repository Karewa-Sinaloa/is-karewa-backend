# Tasks

## 1. Mail environment overrides

- [x] 1.1 Create `app/core/config/mail_env.php` defining the overlay for the documented variables (`MAIL_HOST`, `MAIL_PORT`, `MAIL_SECURITY`, `MAIL_USER`, `MAIL_PASSWORD`, `MAIL_AUTH`, `MAIL_FROM_EMAIL`, `MAIL_FROM_NAME`): a defined variable wins even when empty, an absent variable keeps the file value, `MAIL_PORT` is cast to int, and `MAIL_AUTH` is parsed from a truthy set. Include it from `app/core/config/base.php` and apply the overlay to `$_config->mailing` immediately after the YAML parse. Verify `php -l app/core/config/mail_env.php` and `php -l app/core/config/base.php` pass and `rg "MAIL_HOST" app/core/config/` shows the mapping.
- [x] 1.2 Add `tests/MailConfigEnvTest.php` unit-testing the overlay with an injected environment: defined value (including empty) overrides the file, absent value falls back to the file, a falsey auth value disables authentication, and the port is cast to int. Verify `./vendor/bin/phpunit tests/MailConfigEnvTest.php` passes.

## 2. Local stack wiring and documentation

- [x] 2.1 Add the mail variables to the `php` service `environment:` map in `docker-compose.yml`, sourced from `${MAIL_*:-}` so `.env` values reach the container. Verify `docker compose config` succeeds and shows the eight variables under the `php` service.
- [x] 2.2 Add the Mailpit defaults to `.env.example` (`MAIL_HOST=mailpit`, `MAIL_PORT=1025`, `MAIL_SECURITY=`, `MAIL_USER=`, `MAIL_PASSWORD=`, `MAIL_AUTH=false`, plus the sender address and name). Verify the keys match the names used by `app/core/config/mail_env.php`.
- [x] 2.3 Update `DEV_ENV_MANUAL.md`, `README-docker.md`, and `README.md` to document that local mail is captured by the Mailpit inbox and which variables select it, and remove the dead `app/config.example.yml` reference in `README.md`. Verify every documented variable name matches the loader.

## 3. Integration verification

- [x] 3.1 With `make up`, trigger the login-recovery flow and confirm the message is captured by the Mailpit service and visible in its interface, with no mailer error in `logs/error.log`.
