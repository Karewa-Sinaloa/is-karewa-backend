# Tasks

## 1. Configuration cleanup

- [x] 1.1 Remove the `facebook` section from `app/config.yml` and drop the `facebook:` fixture from `tests/ApiUrlTest.php`; verify `./vendor/bin/phpunit --filter ApiUrlTest` passes and no `facebook` key remains.
- [x] 1.2 Remove the `FB_API_ID`, `FB_API_SECRET`, and `FB_API_VERSION` constants from `app/core/config/base.php`; verify a grep for `FB_API` and `facebook` returns no hits in `app/core`.

## 2. Users module and schema snapshots

- [x] 2.1 Remove the `facebook_id` entry from `$moduleFields` and `$rules` in `app/core/modules/users/controller.php`, and remove `facebook_id` from the `USER_COLUMNS` constant in `tests/UsersFieldMappingTest.php`; verify `./vendor/bin/phpunit --filter UsersFieldMappingTest` passes.
- [x] 2.2 Update the `dev_users` definition and insert statement in `resources/karewa_dev.sql` and `app/karewa_dev_dev.sql` to drop `facebook_id`; verify a grep for `facebook_id` across both files returns no hits.

## 3. Database migration

- [x] 3.1 Add `resources/migrations/drop_users_facebook_id.sql` that drops `dev_users.facebook_id` and is safe to re-run; verify against a test database that the column is gone and a second run does not error.

## 4. API documentation

- [x] 4.1 Remove the `facebook_id` special cases in `tools/generate-openapi.php` and regenerate `api/docs/openapi.json` and `httpdocs/api/docs/openapi.json`; verify a grep for `facebook_id` returns no hits in the generated files.

## 5. Integration verification

- [x] 5.1 Run `./vendor/bin/phpunit` and confirm the full suite passes.
- [x] 5.2 Call the users read and write endpoints and confirm `facebook_id` is neither returned in responses nor required in payloads.
