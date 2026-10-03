# Proposal

## Why

JWT tokens are stateless and remain valid for their entire lifetime: multiple devices can hold concurrent valid tokens for the same user, and there is no way to revoke a token before it expires. A stolen or superseded token therefore stays usable. The product requires a single active token per user, a persistent blacklist, and automatic session cancellation when suspicious token reuse is detected.

## What Changes

- Add a unique token identifier (`jti`) claim to every issued JWT so individual tokens can be tracked and revoked.
- Persist exactly one active session per user and a persistent token blacklist (two new MySQL tables with migrations).
- **BREAKING**: A successful login replaces the user's previous active session; tokens issued before that login stop being accepted.
- Validate every authenticated request against the active session and the blacklist, in addition to the existing JWT signature and expiry checks.
- When a non-active, revoked, or blacklisted token is presented, reject the request, cancel the user's active session, and add the presented token to the blacklist as a suspected replay/theft.
- Implement `AppAccess::Logout()` to cancel the active session and blacklist the current token, replacing the current TODO that returns success without invalidating anything.
- Add API error codes for revoked/superseded sessions.

## Capabilities

### New Capabilities
- `session-management`: single active session per user, JWT-to-session binding, persistent token blacklist, and suspicious-token handling.

### Modified Capabilities
<!-- None. -->

## Impact

- Auth: `app/core/auth/jwt_token.php` (`jti` claim and blacklist check), `app/core/auth/session.set.php` (login/validate/logout lifecycle), `app/core/auth/module.access.controller.php` (session validation on authenticated requests).
- Access module: `app/core/modules/access/local_login.php` (`Login()` and `Logout()`).
- Configuration: `app/config.yml` and `app/core/config/base.php` for session/blacklist settings.
- Database: new `dev_user_sessions` and `dev_token_blacklist` tables plus migrations under `resources/migrations/`; ORM model helpers.
- API responses: new codes in `app/core/config/api_codes.yml`.
- Tests: new session/blacklist coverage and updates to existing JWT/auth tests; `openspec/specs/` gains a `session-management` capability.
