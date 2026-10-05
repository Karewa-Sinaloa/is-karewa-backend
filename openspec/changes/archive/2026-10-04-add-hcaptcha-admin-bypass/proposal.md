# Proposal

## Why

Login always requires a valid hCaptcha token, which makes it impossible to
authenticate from API clients such as Postman or automated test suites without a
browser. At the same time, the current `HCaptcha::Validate()` is not safe against
a malformed or empty hCaptcha response: `json_decode()` can return `null` and
`$body->success` then raises a PHP 8 error instead of failing closed with the
documented `905000` code. Administrators need a controlled, auditable way to skip
the captcha for testing while every other role keeps the protection.

## What Changes

- A new per-user `hcaptcha_bypass` column is added to the `users` table. It stores
  the bypass secret as an irreversible `password_hash` (not plaintext, not
  recoverable).
- A new admin-only endpoint (role `1`) generates and rotates the current
  administrator's bypass secret and returns the plaintext exactly once. The
  secret is only issued to, and only honored for, users with `role_id = 1`.
- At login, when the request carries the secret in the `X-HCaptcha-Bypass`
  header and the identified user is a role-`1` administrator whose stored hash
  matches, the hCaptcha verification is skipped. For every other case the
  hCaptcha requirement is unchanged.
- `HCaptcha::Validate()` is hardened to fail closed: a non-success, empty, or
  malformed upstream response raises `905000` instead of erroring, and the
  result is only accepted when it is a well-formed success payload.
- The bypass mechanism (header name, admin-only scope, endpoint, one-time
  plaintext, and rotation) is documented in `README.md`.
- **BREAKING**: none to current traffic; the bypass is opt-in per administrator
  and captcha verification stays required for every request that does not present
  a valid admin bypass secret.

## Capabilities

### New Capabilities
- `hcaptcha-bypass`: how an administrator's bypass secret is generated, stored
  (hashed), rotated, presented via header, and used to skip login captcha
  verification, plus its README documentation.
- `hcaptcha-verification`: how hCaptcha tokens are validated during login,
  including fail-closed handling of unsuccessful, empty, or malformed upstream
  responses.

### Modified Capabilities
- Ninguna.

## Impact

- `app/core/helpers/hcaptcha.php` — fail-closed hardening of `Validate()`.
- `app/core/modules/access/local_login.php` — resolve the user before/around
  captcha and skip verification for a valid admin bypass header.
- `app/core/modules/users/` (or a dedicated admin endpoint) — generate/rotate
  the bypass secret; `$moduleFields`/validation updated.
- `resources/karewa_dev.sql` and a `resources/migrations/*.sql` migration — new
  `hcaptcha_bypass` column on the users table.
- `app/core/config/api_codes.yml` — response code(s) for the bypass endpoint if a
  new code is required.
- `README.md` — document the bypass for testing/Postman usage.
- Affects the `/api/v5/access` login endpoint and the administrator secret
  endpoint. No dependency changes.
