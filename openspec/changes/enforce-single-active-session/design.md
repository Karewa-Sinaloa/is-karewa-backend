# Design

## Context

See proposal.md - Why. Today `jwtToken::encode()` issues a signed RS256 token with no per-token identity, and `SessionSet::Validate()` only checks signature, `message`, and expiry. `AppAccess::Logout()` is a no-op (explicit TODO). The ORM (`DBGet`/`DBStore`/`DBUpdate`/`DBDelete`) is PDO-based, prepends `MYSQL_PREFIX` to table names, and has no upsert helper. `Ramsey\Uuid` is already a dependency and used in `SessionSet::UniqueIdentifierId()`.

## Goals / Non-Goals

**Goals:**
- Give every issued token a stable identity (`jti`) so it can be tracked and revoked.
- Guarantee one active session per user with a database-backed session record.
- Persist a blacklist and react to suspicious reuse by cancelling the session and blacklisting the token.
- Make logout actually revoke the current token.

**Non-Goals:**
- Replacing JWT with opaque server-side sessions.
- Shortening `SESSION_TIME` or reworking "remember me".
- Rate-limiting or geolocation-based risk scoring (only the single-active-session signal is used).

## Decisions

- **Identity via a `jti` claim.** Add `jti` (UUID v4) to the token payload in `jwtToken::encode()`. Alternative considered: hash the whole token string and use that as the key; rejected because the `jti` is readable before hashing, is portable, and keeps keys smaller and stable.
- **Two tables with distinct jobs.** `dev_user_sessions` holds the single active session per user (unique `user_id`, `jti`, `issued_at`, `expires_at`); `dev_token_blacklist` holds revoked `jti`s (unique `jti`, `user_id`, `expires_at`, `reason`, `revoked_at`, index on `expires_at`). Alternative considered: one table with a `revoked` flag; rejected because the active-session lookup and the blacklist lookup have different cardinalities and lifecycles, and a unique `user_id` on the session table cleanly enforces "one session".
- **Last-write-wins upsert for the session.** Because the ORM lacks upsert, a dedicated `SessionManager` helper issues `INSERT ... ON DUPLICATE KEY UPDATE` (or a delete-then-insert inside a transaction) keyed by `user_id`. This makes a new login atomically replace the old session.
- **Validation order: blacklist, then active session.** `SessionSet::Validate()` (delegating to `SessionManager`) first rejects any `jti` on the blacklist, then loads the user's active session and requires its `jti` to match. A valid signature with a non-matching or missing `jti` is "suspicious": the active session is deleted and the presented `jti` is blacklisted. Alternative considered: reject without side effects; rejected per the chosen reaction policy.
- **Logout reads the Authorization header.** `AppAccess::Logout()` obtains the bearer token, decodes it for its `jti`, deletes the session row, and inserts the `jti` into the blacklist. The access module already routes `index` with `?s=logout` to this method.
- **Pruning by expiry.** A lightweight prune (`DELETE WHERE expires_at < NOW()`) runs opportunistically or on login so the blacklist and session tables do not grow unbounded. Retention is driven by token expiry.
- **New API error code.** Add `901008` (`APP_AUTH_SESSION_REVOKED`, HTTP 401) to `api_codes.yml` for rejected/revoked/superseded tokens, distinct from `901004` login failure.

## Risks / Trade-offs

- [A DB lookup is added to every authenticated request] → Unique indexes on `user_id` and `jti` keep lookups O(1); the query is small.
- [Existing tokens have no `jti` and become invalid at deploy] → Declared **BREAKING**; users must log in again. Document in the release note.
- [Concurrent logins race for the single session slot] → Last writer wins by design; the loser's token is treated as superseded and revoked on next use.
- [Losing tokens forwarded by a client without the header at logout] → Logout still clears the session when a decodable token is present; without a token it cannot blacklist anything, which matches current routing.
- [Blacklist/session tables grow unbounded] → Expiry-based pruning plus the `expires_at` index.

## Migration Plan

1. Deploy migrations creating `dev_user_sessions` and `dev_token_blacklist` (and update the SQL snapshots).
2. Deploy code adding `jti`, `SessionManager`, validation, and logout.
3. Users re-authenticate to receive `jti`-bearing tokens.
4. Rollback: revert code and drop the two new tables; the change is additive to the schema, so token verification returns to the previous stateless behavior (all outstanding tokens remain valid again).

## Open Questions

- None.
