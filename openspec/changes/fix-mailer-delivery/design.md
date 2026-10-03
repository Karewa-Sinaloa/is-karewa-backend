# Design

## Context

See `proposal.md - Why`.

`ApiMailer::Send(array $params)` builds a PHPMailer instance and reads SMTP settings from `json_decode($_apiConfig->smtp_auth)`. The actual configuration in `app/config.yml` is a `mailing` section holding `smtp`, `host`, `port`, `security`, `debug`, `charset`, `ssl_validate`, `user`, `password`, `smtp_auth`, `from_email`, and `from_name`; `smtp_auth` is a boolean, so `json_decode` yields `null` and every property access fails. The helper unconditionally loops `$params['cc']`, `$params['bcc']`, and `$params['attachments']`, but the only caller passes just `from`, `to`, `subject`, `html_body`, and `text_body`. The caller builds the sender as `['email' => $system->email, $system->name]` (the name at index `0`), while the helper reads `$params['from']['name']`. The helper uses `utf8_decode()` (removed in PHP 8.4). It returns nothing on success and throws `AppException(903000)` on failure. `ApiMailer` runs in the `App\Helpers` namespace; `$_apiConfig` is populated by `app/core/helpers/api_configuration.php` from the `config` table.

## Goals / Non-Goals

**Goals:**
- Make a minimal message send without warnings or fatals.
- Source transport settings from the configuration that actually exists.
- Preserve the existing success/failure contract (throw `AppException(903000)` on failure).

**Non-Goals:**
- Redesigning the `mailings` module, its Smarty templates, or its routes.
- Adding a queue, retries, or templating.
- Changing the config schema or rotating credentials.
- Replacing PHPMailer.

## Decisions

- **Guard optional fields with presence checks.** Wrap each of `cc`, `bcc`, `attachments`, and `reply_to` in `if (isset(...) && is_array(...) && $params[...])`. Alternative rejected: defaulting them to empty arrays in every caller, which leaves the helper fragile for future callers.
- **Configure from `$_apiConfig->mailing`.** Read host/port/security/user/password/sender from the `mailing` section, which is the populated source. Alternative rejected: keeping `smtp_auth` JSON, which no config provides.
- **Fix the sender shape at the caller.** Pass `['email' => ..., 'name' => ...]` so both keys exist, and keep the helper reading `from.email`/`from.name`. Alternative rejected: making the helper guess index `0` as the name, which is brittle.
- **Drop `utf8_decode`; set `CharSet = 'UTF-8'`.** Modern PHPMailer handles UTF-8 directly. Alternative rejected: `mb_convert_encoding` to ISO-8859-1, which would mangle content.
- **Keep throwing on failure, but include context.** Retain the `903000` code and the message/recipient context already appended, so callers keep working.

## Risks / Trade-offs

- Reading a different config section could change behavior if `smtp_auth` JSON was populated somewhere else → Mitigation: grep confirms no producer of that JSON; the `mailing` section is the only SMTP config.
- Removing `utf8_decode` changes the byte content of previously (attempted) messages → Mitigation: those messages never sent; UTF-8 is the correct charset for the HTML templates.
- Guarding optional fields could mask a caller intending to send attachments → Mitigation: attachments still apply when provided; only absence is tolerated.
- Tests cannot send real mail → Mitigation: unit-test parameter assembly and configuration reading with a transport stub; leave actual delivery to the Docker integration check.

## Migration Plan

- Deploy the helper and caller together.
- Verify with the Docker stack's Mailpit service that a recovery email is captured.
- Rollback: revert both files; no persisted state changes.

## Open Questions

- None blocking.
