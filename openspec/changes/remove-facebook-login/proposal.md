# Proposal

## Why

The product has decided that Monitor Karewa will authenticate users only with a local email and password. The backend still carries a Facebook SDK configuration, a `facebook_id` user column exposed through the users API and generated docs, and tests that assert them, which wrongly implies a social-login capability that must not exist.

## What Changes

- **BREAKING**: Remove the `facebook` section from `app/config.yml` and the `FB_API_ID`, `FB_API_SECRET`, and `FB_API_VERSION` constants from `app/core/config/base.php`.
- **BREAKING**: Remove the `facebook_id` field from the users module (`$moduleFields` and `$rules`) and drop the `dev_users.facebook_id` column with a migration.
- Remove `facebook_id` from generated API documentation (`api/docs/openapi.json`, `httpdocs/api/docs/openapi.json`, `tools/generate-openapi.php`) and from the `users` example values.
- Update tests that assert the users column set or the config shape (`tests/UsersFieldMappingTest.php`, `tests/ApiUrlTest.php`) so they no longer reference Facebook.
- Confirm and document that the only login path is local email + password; no social/OAuth provider login is offered.

## Capabilities

### New Capabilities
<!-- None: this change only removes behavior and references. -->

### Modified Capabilities
- `authentication-and-authorization`: authentication is local email + password only; no social/OAuth provider login SHALL be offered, and the user identity SHALL NOT include a Facebook identifier.
- `configuration-and-logging`: the `facebook` configuration section is removed from the set of required configuration sections.

## Impact

- Configuration: `app/config.yml`, `app/core/config/base.php`.
- API/users module: `app/core/modules/users/controller.php`; `facebook_id` disappears from user read/write payloads and filters.
- Database: `resources/karewa_dev.sql`, `app/karewa_dev_dev.sql` schema snapshots, plus a new migration dropping `dev_users.facebook_id`; existing Facebook identifier data is lost.
- API docs: `api/docs/openapi.json`, `httpdocs/api/docs/openapi.json`, `tools/generate-openapi.php`.
- Tests: `tests/UsersFieldMappingTest.php`, `tests/ApiUrlTest.php`.
- Specs: `openspec/specs/authentication-and-authorization/spec.md`, `openspec/specs/configuration-and-logging/spec.md`.
