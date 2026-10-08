# Tasks

## 1. Runtime disclosure in the response path

- [x] 1.1 Add a static `ApiResponse::SetAllowedRoles()` publisher, include `allowed_roles` in the reserved-key list of the extra-data merge, and inject the stored value into the envelope when `AUTHENTICATED` is defined and true; verify with new cases in `tests/ApiResponseTest.php` (field present when authenticated, absent when not, module data cannot override it) passing via `./vendor/bin/phpunit --filter ApiResponseTest`.
- [x] 1.2 In `ModuleHandler::Validate()`, aggregate the module's `store`/`update`/`destroy` role declarations into `{create, edit, delete}` (integer IDs, undeclared actions omitted, empty array for no restriction) and publish it before authentication runs; verify with a test covering a module declaring all three actions, only some, and none (empty object) passing via `./vendor/bin/phpunit`.
- [x] 1.3 Verify the presence rule end to end against `specs/allowed-roles-disclosure/spec.md`: an authenticated method's success and in-method error responses carry `allowed_roles`, a public method with a valid token carries it, and anonymous calls plus rejected authentications (401/403) do not; verify by a PHPUnit test exercising `ModuleHandler::Validate()` flows passing via `./vendor/bin/phpunit`.

## 2. OpenAPI contract

- [x] 2.1 Extend `tools/generate-openapi.php` so operations declaring the bearer security scheme document an optional `allowed_roles` object property (`create`/`edit`/`delete` arrays of integers) on their success-response envelope schema, and operations without security omit it; verify by running `php tools/generate-openapi.php` and inspecting the generated document for one secured (`GET /users`) and one anonymous (`GET /config/{id}`) operation.
- [x] 2.2 Regenerate and publish the contract to `api/docs/openapi.json` and its `httpdocs/api/docs/` mirror; verify with `./vendor/bin/phpunit --filter OpenApiSyncTest` passing (document matches generator output).

## 3. Integration checks

- [x] 3.1 Run the full suite `./vendor/bin/phpunit` and confirm every test passes with the new field present.
- [x] 3.2 Smoke-test a live request pair: an authenticated call's JSON contains top-level `allowed_roles` next to `message`/`code`, and the same endpoint without a token contains no `allowed_roles` key; verify by curl output inspection against the local stack (`make up` if needed).
