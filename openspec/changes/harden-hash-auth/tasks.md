# Tasks

## 1. Token minting and validation in HashAuth

- [ ] 1.1 In `app/core/auth/hash.auth.php`, add a private helper that encodes a payload array (resource fields plus `exp`) into the signed string, and use it from both `Create` and `Validate` so the encodings cannot drift. Verify `php -l` passes and both methods call the same helper.
- [ ] 1.2 Rework `Create` to mint a token over the payload with an expiration derived from a configurable window, preserving URL-safe output. Verify a round-trip test (`Create` then `Validate` with the same payload) succeeds.
- [ ] 1.3 Rework `Validate` to verify against the caller-supplied payload, reject payload mismatches, reject expired tokens, and read the witness from a header first with a `_key` fallback. Verify `php -l` passes.
- [ ] 1.4 Add `tests/HashAuthTest.php` covering: round trip succeeds; different payload fails; expired token fails; header witness works; `_key` fallback works; missing witness fails. Verify `./vendor/bin/phpunit tests/HashAuthTest.php` passes.

## 2. Configuration

- [ ] 2.1 Add the expiration window to `app/config.yml` / `app/core/config/base.php` (with a sane default) and read it where `HashAuth` mints tokens. Verify the default applies when the value is absent.
- [ ] 2.2 Document the token format, expiration, and header/`?_key` usage in an auth section of the project docs. Verify the documented example matches the implemented helper behavior.

## 3. Re-enable alternative authentication

- [ ] 3.1 In `app/core/auth/module.access.controller.php`, restore the alternative-auth branch so a non-empty hash is validated and, when valid, grants access and defines `AUTHENTICATED` without requiring a session token. Verify `php -l` passes and the commented block is removed.
- [ ] 3.2 Verify the existing `mailings/index.php` hash slot flows into `$hash` and is now consumed, without changing the `mailings`/`login-recovery` behavior. Confirm by inspection that no `mailings` file changed.
- [ ] 3.3 Add `tests/ModuleHashAuthTest.php` asserting a valid hash grants access to a hash-declaring method without a session token, and an invalid/missing hash denies access. Verify `./vendor/bin/phpunit tests/ModuleHashAuthTest.php` passes.

## 4. Integration verification

- [ ] 4.1 Run the full suite with `./vendor/bin/phpunit` (or `make test`) and confirm all tests pass, including existing auth/route tests.
- [ ] 4.2 With `make up`, confirm normal session-authenticated endpoints behave exactly as before, and that a hash-declaring method rejects requests without a valid hash.
