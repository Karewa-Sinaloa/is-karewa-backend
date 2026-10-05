# Design

## Context

See `proposal.md - Why`.

`HashAuth::Create(array $hashArray)` concatenates the payload with `implode($hashArray)` (empty glue), runs `password_hash(HASH_AUTH_PASS . $hashString, PASSWORD_BCRYPT)`, strips the `$2y$10$` prefix, base64-encodes, and trims `=`. `Validate(array $hashArray)` concatenates its argument with `implode('', ...)`, ignores it, reads `$_GET['_key']`, base64-decodes, rebuilds `$2y$10$...`, and `password_verify`s. `HASH_AUTH_PASS` is defined in `app/core/config/base.php` from `$_config->hash`. `ModuleHandler::Authenticate` has the consuming branch commented out; `mailings/index.php` passes `['hash' => $_GET['_key']]` in the third slot of its `index` method config, which becomes `$hash` and is currently unused. The `mailings`/`login-recovery` flow builds a `recovery_hash` and a `_key` but never validates the key.

## Goals / Non-Goals

**Goals:**
- Make a minted token unusable for any resource other than the one it was minted for.
- Add expiration without persistence.
- Read the witness from a header with a `_key` fallback.
- Re-enable the alternative-auth branch so a module can use it, without changing current traffic.

**Non-Goals:**
- Migrating `mailings`/`login-recovery` to real validation; its flow is unchanged.
- Introducing a token store, revocation list, or refresh mechanism.
- Changing JWT/session authentication.
- IP allow-listing (a separate webhook concern).

## Decisions

- **Bind the token with an HMAC-style payload digest, not a bare `password_hash` of a secret.** Mint over an encoded payload that includes the resource fields and an expiration, then verify the presented payload matches and the signature verifies. This makes cross-resource reuse fail structurally. Alternative rejected: keep `password_hash` but verify the caller-supplied payload, which still lets a base64 token be replayed for a guessed payload unless the payload is inside the signed material.
- **Put expiration in the signed payload.** `Create` embeds an `exp` derived from a configurable window; `Validate` rejects when now is past it. No storage needed. Alternative rejected: a DB column, which changes the schema and is unnecessary for links/webhooks.
- **Read the witness from a header, fall back to `_key`.** Reuse the existing header-reading style (`apache_request_headers()`) already used for `Authorization`. Alternative rejected: header-only, which breaks existing `_key` links before migration.
- **One encoding scheme on both sides.** Extract a private helper that builds the signed string from the payload array so `Create` and `Validate` cannot drift. Alternative rejected: leaving two `implode` calls, which is the current bug.
- **Re-enable the branch guarded by a non-empty hash.** Restore the `if (!empty($hash)) { $altAuth = HashAuth::Validate(...); }` block and pass the token/payload the module already supplies. Do not change the session path.

## Risks / Trade-offs

- Changing the token format invalidates any tokens minted by the old code → Mitigation: the feature is inert today, so there are no live tokens; document the format change.
- Reading a header may be absent under some SAPIs → Mitigation: keep the `_key` fallback and treat a missing witness as validation failure.
- Expiration uses wall-clock time and could reject legitimate slow links → Mitigation: make the window configurable.
- Re-enabling the branch could grant access if a module passes an unintended hash → Mitigation: only modules that explicitly declare the hash slot get the path, and validation now requires a payload match.

## Migration Plan

- Deploy the class and the re-enabled branch together; keep `mailings` untouched.
- Add the expiration window to config with a sane default.
- Rollback: restore the commented branch and the prior class; no persisted state changes.

## Open Questions

- None blocking. The exact expiration default can be chosen during implementation.
