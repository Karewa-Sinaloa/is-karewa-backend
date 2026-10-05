# Design

## Context

See `proposal.md` — Why. Relevant current state:

- `app/core/modules/access/local_login.php` validates the request fields, then calls
  `HCaptcha::Validate($this->payload->token)` *before* it looks up the user by
  email. Any bypass decision needs the user's role, so the lookup must move ahead
  of the captcha step for the bypass path.
- `app/core/helpers/hcaptcha.php` post-processes the upstream response with
  `json_decode()` and reads `$body->success` without guarding for a non-object.
- The `users` table (`dev_users`) has no bypass column; credential-like values
  (`password`, `recovery_code`) are stored with `password_hash`.
- No encryption helper or app-key management exists in the codebase.
- Module auth is declarative: `index.php` passes `$accepted_methods` to
  `ModuleHandler::Validate()`, and role IDs gate access (`authentication-and-authorization`).

## Goals / Non-Goals

Goals:
- Let an administrator authenticate without solving a captcha by presenting a
  per-user secret, for Postman/testing and trusted API clients.
- Keep the secret unusable to anyone who can read the database.
- Make captcha verification fail closed and never crash on a bad upstream body.

Non-Goals:
- No change to captcha requirements for non-administrator roles.
- No request-wide/global bypass; the bypass is per administrator and per request.
- No plaintext serialized (reversible) storage of the secret.
- No UI; the secret is issued through an API operation and documented for CLI/HTTP clients.

## Decisions

### Store the secret as an irreversible hash
Use `password_hash(..., PASSWORD_DEFAULT)` and verify with `password_verify`,
mirroring `password` and `recovery_code`. The secret is a bearer credential used
only for equality checking, so a slow hash is the right primitive.

Alternative considered: reversible `openssl` AES encrypted with an app key.
Rejected because it would require introducing key management and an encryption
helper, and because recovering the plaintext is never needed — rotation covers
loss.

### New nullable `users.hcaptcha_bypass` column
Add `hcaptcha_bypass VARCHAR(255) DEFAULT NULL` to `dev_users`, plus a
`resources/migrations/*.sql` migration. A `NULL` value means "no bypass
configured" and no administrator is bypassable until they generate one.

### Declare the column as never listed, filtered, or saved by the users module
Add it to `$moduleFields` with `listed => false`, `filter => false`,
`saved => false` so the existing generic user endpoints can never read or write
it. Only the dedicated generation operation writes it.

### Dedicated administrator-only operation to generate/rotate
Expose generation as a new authenticated operation (its own module route resolved
by `routes.php`, `POST` mapped to `store`), declared `[true, [1]]` in its
`index.php` so `ModuleHandler::Validate()` rejects every non-administrator before
the method runs. The method generates `random_bytes(32)`, stores the hash against
`USER_ID`, replaces any previous value, and returns the plaintext/base64url
secret exactly once.

Alternative considered: a branch inside the existing `users` module. Rejected
because that module's public `store` is unauthenticated (self-registration) and
its `update` is reused for profile edits, so mixing in a credential-generation
operation there is easy to mis-gate.

### Resolve the user, then decide the captcha path
Reorder login: validate fields, look up the enabled user by email, then:
1. If the `X-HCaptcha-Bypass` header is present **and** the user's `role_id` is
   `1` **and** the header value verifies against the stored hash, skip captcha.
2. Otherwise run `HCaptcha::Validate()` as today (fail closed) and, on success,
   continue.

The user lookup is already part of login; moving it earlier does not change the
credentials result. Non-bypass requests keep an identical outcome, and every
failure still returns the generic authentication error so the bypass is not a
user-existence oracle.

### Fail closed in `HCaptcha::Validate()`
Treat the upstream call as successful only when the response decodes to an object
with `success === true`. On transport failure, empty/undecodable body, or a
non-success flag, throw the existing `905000` third-party error. Never
dereference a possibly-null decoded body.

## Risks / Trade-offs

- [Leaked admin secret weakens captcha for that account] → stored hashed so DB
reads do not yield it; administrators can rotate on demand; the bypass is
administrator-only and documented as a testing/trusted-client tool.
- [Extra `password_verify` cost per login attempt that sends the header] → only
computed when the header is present; acceptable for an admin-only path.
- [Timing/oracle differences when user lookup precedes captcha] → uniform generic
error responses on every failure path; no distinct message for unknown users or
wrong secrets.
- [Confirming the safe algorithm] → use `PASSWORD_DEFAULT` (currently bcrypt) and
let PHP migrate the algorithm; no hard-coded algorithm assumptions in the comparison.

## Migration Plan

1. Land the migration adding the nullable `hcaptcha_bypass` column and apply it.
2. Deploy code: fail-closed `HCaptcha::Validate()`, reordered login, the
   generation operation, and `$moduleFields` flags.
3. Administrators call the generation operation to obtain a secret, then use it
   via the `X-HCaptcha-Bypass` header; the column stays `NULL` until then.

Rollback: revert the code (captcha applies normally again), then drop the column.
No data backfill is required because `NULL` disables the bypass.

## Open Questions

None.
