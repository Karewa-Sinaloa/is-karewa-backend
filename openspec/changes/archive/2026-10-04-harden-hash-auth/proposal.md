# Proposal

## Why

Alternative hash authentication exists but is non-functional. The `ModuleHandler::Authenticate` branch that consumes it is commented out, so `$altAuth` is always null and the hash parameter is computed and ignored. `HashAuth::Validate()` reads the witness from `$_GET['_key']`, ignores both the hash it is passed and the resource payload, uses an `implode` glue inconsistent with `HashAuth::Create()`, and has no expiration. A hash minted for one resource can therefore satisfy any other. The feature is intended for future webhooks and must be made safe to enable before anyone relies on it.

## What Changes

- `HashAuth` mints and validates a token bound to a payload, so a token for one resource cannot authenticate another.
- Tokens carry an expiration and validation rejects expired ones.
- Validation accepts the witness from a request header, with a fallback to the existing `?_key` query parameter so existing links keep working.
- `ModuleHandler::Authenticate`'s alternative-auth branch is re-enabled and uses the hash passed from `index.php`, so a module can actually opt into hash auth.
- `HashAuth::Create` and `HashAuth::Validate` use one consistent payload-encoding scheme.
- The `mailings`/`login-recovery` flow is out of scope and unchanged.
- **BREAKING**: none to current traffic; the feature is currently inert. When enabled, tokens are bound to a payload and expire, which changes how callers must mint and present them.

## Capabilities

### New Capabilities
- `hash-authentication`: how a link/webhook token is minted, bound to a resource, expires, and is validated from a request.

### Modified Capabilities
- Ninguna.

## Impact

- `app/core/auth/hash.auth.php` — token minting, payload binding, expiration, header/`?_key` source.
- `app/core/auth/module.access.controller.php` — re-enable and correct the alternative-auth branch.
- `app/core/config/base.php` / `app/config.yml` — an expiration window value may be needed.
- `mailings` module: explicitly unchanged; its `_key` generation is not validated today and remains so.
- No schema change; no dependency change.
