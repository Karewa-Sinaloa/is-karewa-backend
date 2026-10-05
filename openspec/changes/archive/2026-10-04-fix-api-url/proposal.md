# Proposal

## Why

`app/core/config/base.php` defines `API_URL` from the `api` configuration node,
but that node is a map (`api.url`, `api.https`), not a string. `API_URL` is
therefore a `stdClass`, and the first place it is concatenated —
`loginMailer` building the recovery link in the `access` module — fails with
`Object of class stdClass could not be converted to string`. Account recovery
cannot build its email link, so the flow dies after writing the recovery code.

## What Changes

- `API_URL` is defined as a real URL string built from the `api` configuration
  (its `url` plus the scheme implied by `api.https`), instead of the raw `api`
  object.
- Observable behavior: `POST /api/v5/access/recovery` no longer fails converting
  `API_URL` to string.
- No route, response-code, prefix, schema, or dependency changes.
- **BREAKING**: none.

Explicit non-goal: the mailer's SMTP transport, optional-field handling, charset,
and sender shape are owned by `fix-mailer-delivery`. This change only corrects the
`API_URL` constant.

## Capabilities

### New Capabilities
- `api-url-configuration`: how the API derives its public base URL constant from
  configuration.

### Modified Capabilities
- Ninguna.

## Impact

- `app/core/config/base.php` — the `API_URL` definition.
- Affects any consumer of `API_URL` (currently `access`'s recovery-link builder).
- No schema or dependency changes.
