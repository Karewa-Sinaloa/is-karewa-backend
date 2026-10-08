# Proposal

## Why

Clients (the Vue.js frontend and third-party integrators) cannot currently tell which roles are allowed to create, edit or delete a resource without hard-coding role knowledge per endpoint. Exposing the module's declared role lists on authenticated responses lets clients enable or disable actions dynamically, and the public OpenAPI contract must describe the same field so integrators can rely on it.

## What Changes

- Every API response served for an authenticated request (`AUTHENTICATED === true` at response time) gains a top-level `allowed_roles` field alongside `message`, `code`, `http_code` and `meta`.
- `allowed_roles` is an object keyed by action — `create`, `edit`, `delete` — mapped from the module's declared `store`, `update` and `destroy` entries in `$accepted_methods`; each value is the array of allowed role IDs (an empty array means "any authenticated role"). Keys for actions the module does not declare are omitted.
- Responses served for anonymous (unauthenticated) requests never include `allowed_roles` — including public methods called without a token, and authentication failures (401/403) where authentication was not established.
- `allowed_roles` becomes a reserved envelope key: extra data supplied by modules cannot override it.
- The published OpenAPI document (`api/docs/openapi.json`) documents `allowed_roles` as a conditional envelope property on operations that require authentication, and the automated contract-sync check keeps passing.
- The behavior is specified as a separate capability so it can be implemented and reviewed on its own.

## Capabilities

### New Capabilities

- `allowed-roles-disclosure`: Runtime behavior of the `allowed_roles` envelope field — presence on authenticated responses, absence on anonymous responses, shape and derivation from module `$accepted_methods`, and reserved-field protection.

### Modified Capabilities

- `api-response-envelope`: The reserved-envelope-fields requirement adds `allowed_roles` so extra data can never replace or spoof the disclosure field.
- `swagger-scalar-api-docs`: The envelope-schema requirement and operation documentation cover the conditional `allowed_roles` property (present on authenticated operations, absent on anonymous ones), keeping the contract in sync with the runtime.

## Impact

- `app/core/helpers/api_response.php` (`ApiResponse::Set()`) — injects the field and adds it to the reserved-key list.
- `app/core/auth/module.access.controller.php` (`ModuleHandler::Validate()`) — publishes the module's declared CRUD role lists for the current request.
- `tools/generate-openapi.php` and `api/docs/openapi.json` (mirrored at `httpdocs/api/docs/openapi.json`) — document the conditional property.
- `tests/OpenApiSyncTest.php`, `tests/ApiResponseTest.php` — coverage for the new field and contract sync.
- API consumers: additive, non-breaking response change (new optional top-level field).
