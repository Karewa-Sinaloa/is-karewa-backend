# Tasks

## 1. Fix double prefix in the association guard

- [x] 1.1 In `app/core/model/delete.php`, change `DBDelete::is_asociated()` so it passes the bare `$value['table']` to `DBGet::Get()` (remove the `MYSQL_PREFIX .` concatenation). Verify with `php -l app/core/model/delete.php` and confirm no `MYSQL_PREFIX` reference remains inside `is_asociated()`.
- [x] 1.2 Add a regression test `tests/DeleteAssociationGuardTest.php` that invokes `DBDelete::delete()` with a `$table_assoc` entry against the development database (skip with `markTestSkipped` when the database is unreachable) and asserts no doubly-prefixed identifier is issued. Verify with `./vendor/bin/phpunit tests/DeleteAssociationGuardTest.php` (or `make test`).

## 2. Fix the users association key value

- [x] 2.1 In `app/core/modules/users/controller.php`, set the `$table_assoc` `value` to `$_GET['id']` instead of the undefined `$id`. Verify with `php -l app/core/modules/users/controller.php` and confirm the `Undefined variable '$id'` diagnostic at that line is gone.
- [x] 2.2 Add a regression test in `tests/DeleteAssociationGuardTest.php` asserting that a user referenced by a `customers.user_id` row is blocked with `902002` while an unreferenced user deletes normally. Verify the test passes, or, if the database is unavailable, verify the same assertion through the running API (task 3.1).

## 3. Integration verification

- [x] 3.1 With `make up`, prefix `dev_`, and at least one associated row present, exercise `DELETE /api/v5/{module}/{id}` for a referenced and an unreferenced record and confirm HTTP 409 `APP_ENTRY_RELATIONSHIP` versus a successful delete. Verify no `Table 'dev_dev_...' doesn't exist` entry appears in `logs/error.log`.
- [x] 3.2 Update `orm_wiki.md` in the `$table_assoc` section to state that associated table names are declared bare and that `DBGet` owns prefixing. Verify the documented example matches the corrected code.
