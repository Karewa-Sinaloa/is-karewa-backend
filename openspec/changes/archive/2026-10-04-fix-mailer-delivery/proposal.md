# Proposal

## Why

Outgoing mail is broken. `ApiMailer::Send` iterates `cc`, `bcc`, and `attachments` without checking they exist, while its only caller passes none of them, so sending raises warnings or fatals under PHP 8. It reads the sender name from a key the caller never sets, decodes the subject and body with the removed `utf8_decode()`, and parses SMTP credentials from a config value that does not hold them. The recovery email path therefore cannot deliver.

## What Changes

- Optional message fields (`cc`, `bcc`, `attachments`, `reply_to`) are used only when provided.
- The sender address and name are read correctly from what the caller supplies.
- SMTP settings are read from the `mailing.*` configuration section that actually holds the host, port, security, credentials, and sender.
- The deprecated `utf8_decode()` calls are removed and mail is sent as UTF-8 with an explicit charset.
- The caller builds the sender and optional fields in the shape the mailer expects.
- **BREAKING**: none. The mailer still sends the same messages; it stops crashing on absent optional fields.

## Capabilities

### New Capabilities
- `mail-delivery`: how the shared mail helper builds and sends a message from its parameters and configuration.

### Modified Capabilities
- Ninguna.

## Impact

- `app/core/helpers/phpmailer.php` — parameter handling, config source, charset.
- `app/core/modules/access/local_login.php` — the sender/optional-field shape it passes to the mailer.
- `app/config.yml` — consumed as-is from `mailing.*`; no schema change. Its `smtp_auth` key is left untouched but no longer parsed as JSON by the mailer.
- Interacts with `harden-hash-auth` only through the same login flow; neither changes the other.
- No dependency changes.
