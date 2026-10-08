# Tasks

## 1. Align the users module field map

- [x] 1.1 In `app/core/modules/users/controller.php`, change the `recovery_date`
  field from `'field' => 'u.recovery_datetime'` to
  `'field' => 'u.recovery_date'`. Verify `php -l` passes and `rg
  "recovery_datetime" app/core/modules/users/` returns no matches.
- [x] 1.2 In the same file, remove the `phone_country_code`, `photo`, and
  `email_verified` entries from `$moduleFields` and remove their validation rules
  (`phone_country_code`, `email_verified`). Verify none of the three names appear
  in `app/core/modules/users/controller.php`.
- [x] 1.3 In the same file, add `middle_name` and `second_last_name` fields
  mapped to `u.middle_name` and `u.second_last_name`. Verify the field map names
  match the columns defined in `dev_users`.

## 2. Tests and documentation

- [x] 2.1 Add `tests/UsersFieldMappingTest.php` that requires
  `CORE_PATH . 'bootstrap/midelware.php'` and the users controller, then asserts
  (via reflection on `$moduleFields`) that every declared `field` references a
  column present in the users schema, that `recovery_date` is mapped, that
  `phone_country_code`/`photo`/`email_verified` are absent, and that
  `middle_name`/`second_last_name` are present. Verify
  `./vendor/bin/phpunit tests/UsersFieldMappingTest.php` passes.
- [x] 2.2 Update `orm_wiki.md` (or the module conventions spec if more apt) to
  note that `$moduleFields` must only reference existing columns. Verify the
  documented guidance matches the corrected code.

## 3. Integration verification

- [x] 3.1 Run the full suite with `./vendor/bin/phpunit` (or `make test`) and
  confirm all tests pass.
- [x] 3.2 With `make up` and prefix `dev_`, call `GET /api/v5/users` with an
  authorized token and confirm it returns the standard success envelope, with no
  `Unknown column` entry in `logs/error.log`/`logs/debug.log`.
