# Proposal

## Why

The local Docker stack already runs Mailpit, but the application's outgoing mail never reaches it: the `mailing` section in the gitignored `app/config.yml` points at the production provider (SendGrid) and there is no committed or environment-driven way to switch a developer's checkout to Mailpit. The `app/config.example.yml` that `README.md` tells developers to copy does not exist. As a result, testing the recovery and notification flows locally requires editing gitignored files, and a fresh environment silently attempts real production SMTP.

## What Changes

- The configuration loader resolves a defined set of environment variables over the existing `mailing` section: `MAIL_HOST`, `MAIL_PORT`, `MAIL_SECURITY`, `MAIL_USER`, `MAIL_PASSWORD`, `MAIL_AUTH`, `MAIL_FROM_EMAIL`, and `MAIL_FROM_NAME`. Environment values take precedence over `app/config.yml`; unset variables leave the file values untouched.
- The tracked `.env.example` gains the Mailpit defaults for the local stack (`MAIL_HOST=mailpit`, `MAIL_PORT=1025`, no security, no auth), so `cp .env.example .env` is enough to route local mail to Mailpit.
- Documentation (`DEV_ENV_MANUAL.md`, `README-docker.md`, `README.md`) states that local mail is captured by Mailpit through these variables and how to view it, and stops referencing the non-existent `app/config.example.yml`.
- Production behavior is unchanged when the variables are unset; no credential is committed.
- **BREAKING**: none.

## Capabilities

### New Capabilities
- Ninguna.

### Modified Capabilities
- `configuration-and-logging`: configuration loading gains environment-variable overrides with precedence over `app/config.yml` for the `mailing` section.
- `development-setup`: the local stack routes application mail through the Mailpit service using the existing `mailing` configuration, and the tracked environment example carries the values that make that work.

## Impact

- `app/core/config/base.php` — resolve the mail environment overrides before defining the mailing runtime values.
- `.env.example` — add the Mailpit mail variables (no secrets).
- `DEV_ENV_MANUAL.md`, `README-docker.md`, `README.md` — document the local Mailpit mail path and remove the dead `app/config.example.yml` reference.
- Tests — a config-resolution test proving env values override file values and that unset variables preserve them.
- Consumes the existing `mailing` configuration and the existing `mailpit` Docker service; no new service, dependency, or schema change.
