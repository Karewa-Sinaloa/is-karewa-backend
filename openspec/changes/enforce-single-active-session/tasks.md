# Tasks

## 1. Database schema

- [ ] 1.1 Add `resources/migrations/user_sessions.sql` and `resources/migrations/token_blacklist.sql` creating `dev_user_sessions` (unique `user_id`, `jti`, `issued_at`, `expires_at`) and `dev_token_blacklist` (unique `jti`, `user_id`, `expires_at`, `reason`, `revoked_at`, index on `expires_at`); verify both create cleanly on a test database and are safe to re-run.
- [ ] 1.2 Add the two table definitions to the `resources/karewa_dev.sql` and `app/karewa_dev_dev.sql` snapshots; verify both snapshots contain the new tables and their keys.

## 2. Token identity and error code

- [ ] 2.1 Add a unique `jti` (UUID v4) claim to the payload in `jwtToken::encode()` and surface it in the returned object; verify a test asserts every encoded token carries a non-empty `jti` that differs between calls and still decodes.
- [ ] 2.2 Add code `901008` (`APP_AUTH_SESSION_REVOKED`, HTTP 401) to `app/core/config/api_codes.yml` and a configurable blacklist retention value under `session` in `app/config.yml` / `app/core/config/base.php`; verify config loads and the code resolves through `ApiResponse`.
- [ ] 2.3 Update `tests/JWTKeyEncodeTest.php` for the new `jti` claim; verify `./vendor/bin/phpunit --filter JWTKeyEncodeTest` passes.

## 3. Session manager and validation

- [ ] 3.1 Add a `SessionManager` helper that replaces a user's active session atomically (unique `user_id` upsert), checks whether a `jti` is the active session, inserts blacklist entries, cancels the active session, and prunes expired rows; verify unit tests cover replace-last-wins, active lookup, cancel, and prune against a test database.
- [ ] 3.2 Store the active session on login in `SessionSet::Login()` and wire `SessionSet::Validate()` to reject blacklisted tokens and require the token `jti` to match the active session; verify tests show the active token is accepted, while a superseded token is rejected and triggers session cancellation plus a blacklist entry.
- [ ] 3.3 Return `901008` for revoked/superseded tokens in `app/core/auth/session.set.php` while leaving signature/expiry errors on their existing codes; verify a test asserts the distinct error code.

## 4. Logout revocation

- [ ] 4.1 Implement `AppAccess::Logout()` in `app/core/modules/access/local_login.php` to read the bearer token, cancel the active session, and blacklist its `jti`; verify a test confirms the token is rejected after logout.
- [ ] 4.2 Confirm the `?s=logout` route still returns success when authenticated and does not error when no token is present; verify with a test through the access module entry point.

## 5. Integration verification

- [ ] 5.1 Run `./vendor/bin/phpunit` and confirm the full suite passes.
- [ ] 5.2 Exercise the flow over HTTP: log in twice, confirm the first token is rejected and the active session is cancelled, then log out and confirm the latest token is rejected as revoked or blacklisted.
