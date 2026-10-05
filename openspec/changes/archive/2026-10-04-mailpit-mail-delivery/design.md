# Design

## Context

See `proposal.md - Why`. The application parses `app/config.yml` into the global `$_config` in `app/core/config/base.php`, and the mail helper reads the `mailing` section from `$_config` (see the `mail-delivery` spec). `docker-compose.yml` already defines the `mailpit` service on the shared `app_net` network, so the only missing link is the application's mail target.

`app/config.yml` is gitignored and holds real credentials; `.env` is also gitignored but `.env.example` is tracked and Docker Compose reads `.env` for interpolation. The `php` service currently receives a fixed `environment:` list, and the official PHP image sets `clear_env = no` with `variables_order = EGPCS`, so variables passed to the container are visible to PHP-FPM.

## Goals / Non-Goals

**Goals:**
- Let an environment select a different mail target through documented variables, without editing `app/config.yml`.
- Make Mailpit the local target through the tracked `.env.example`, with no committed secret.
- Keep production behavior identical when the variables are absent.

**Non-Goals:**
- Changing the mail helper contract, the Mailpit service definition, or the config file schema.
- Adding a mail queue, retries, or template changes.
- Reading SMTP settings from the database (`$_apiConfig`).

## Decisions

- **Overlay overrides on `$_config->mailing` in `base.php`, right after the YAML parse.** This is the single place every consumer of the `mailing` section reads from, so the effective values are consistent. Alternative rejected: resolving the variables only inside the mail helper, which would scatter configuration logic and miss future readers.
- **A defined variable wins, even when empty; an absent variable keeps the file value.** Presence is tested with `getenv($name) !== false`. This is required because Mailpit needs `MAIL_SECURITY=` (no TLS) and `MAIL_AUTH=false`; treating empty as "unset" could not express plain SMTP. Alternative rejected: treating empty as unset, which cannot disable TLS.
- **Documented variable set:** `MAIL_HOST`, `MAIL_PORT`, `MAIL_SECURITY`, `MAIL_USER`, `MAIL_PASSWORD`, `MAIL_AUTH`, `MAIL_FROM_EMAIL`, `MAIL_FROM_NAME`. `MAIL_AUTH` is parsed to a boolean from a truthy set (`1`, `true`, `yes`, `on`). `MAIL_PORT` is cast to an integer.
- **Pass the variables into the `php` container explicitly in `docker-compose.yml`.** Listing the mail variables keeps the injection narrow and readable. Alternative rejected: `env_file: .env`, which would expose every tracked `.env` value, including database credentials, to the application process.
- **Extract the overlay into a small, testable function.** `base.php` defines PHP constants and cannot be included twice, so the overlay lives in a function that accepts the `mailing` object and an environment lookup, and is covered by a unit test that injects values. Alternative rejected: requiring `base.php` in tests, which is not re-includable.
- **Document the Mailpit defaults in `.env.example` and the manuals**, and remove the dead `app/config.example.yml` reference from `README.md`.

## Risks / Trade-offs

- PHP-FPM could clear the environment and make the variables invisible → Mitigation: the official image sets `clear_env = no` (verified in the running container); the design keeps the explicit `environment:` injection so the variables are always present.
- A defined-but-empty variable now overrides a file value (for example `MAIL_USER=`) → Mitigation: only the documented variables take part, and the behavior is stated in the spec and the manuals.
- The `php` service passes the variables unconditionally, so a `.env` that omits them resolves each to empty and blanks the mail settings → Mitigation: `.env.example` carries the Mailpit values and the manuals state that existing `.env` files must add the keys; the values were never silently omitted.
- Overrides are resolved at bootstrap, not per request → Mitigation: this matches how the rest of the configuration is loaded; changing a variable requires a container restart, which the manual notes.

## Migration Plan

- Ship `base.php`, `docker-compose.yml`, `.env.example`, and the documentation together.
- Non-Docker deployments that define no `MAIL_*` variables keep their `app/config.yml` behavior.
- Docker developers must add the `MAIL_*` block from `.env.example` to `.env`; the `php` service passes those variables unconditionally, so a missing key resolves to empty. New checkouts get them by copying `.env.example`.
- Rollback: revert the files; there is no schema change and no persisted state.

## Open Questions

- None.
