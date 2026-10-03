# Tasks

## 1. Align the roles module table name

- [x] 1.1 In `app/core/modules/roles/controller.php`, change `$get_params['table']` from `'user_roles'` to `'roles'`. Verify `php -l app/core/modules/roles/controller.php` passes and `rg "user_roles" app/core/modules/roles/` returns no matches.

## 2. Align the users module table names and rules

- [x] 2.1 In `app/core/modules/users/controller.php`, change the join table `'user_roles r'` to `'roles r'` and `'user_status s'` to `'users_status s'`. Verify the join aliases `r`/`s` are still present and `php -l` passes.
- [x] 2.2 In the same file, change the validation rules `exist:user_status:id` to `exist:users_status:id` and `exist:user_roles:id` to `exist:roles:id`. Verify `rg "user_roles|user_status" app/core/modules/users/` returns no matches.

## 3. Tests and documentation

- [x] 3.1 Add `tests/RoleTableNamingTest.php` that requires `CORE_PATH . 'bootstrap/midelware.php'`, `CORE_PATH . 'model/get.php'`, and both controllers, then asserts (via reflection on `$get_params`) that `roles` uses table `roles` and `users` joins `roles` and `users_status`, with no reference to the old names. Verify `./vendor/bin/phpunit tests/RoleTableNamingTest.php` passes.
- [x] 3.2 Update `orm_wiki.md` so the documented example table names match the canonical base names (`roles`, `users_status`) and note that the prefix is applied once by `DBGet`. Verify the documented names match the corrected code.

## 4. Integration verification

- [x] 4.1 Run the full suite with `./vendor/bin/phpunit` (or `make test`) and confirm all tests pass.
- [ ] 4.2 With `make up` and prefix `dev_`, call `GET /api/v5/roles` and confirm the query targets `dev_roles` and returns the standard success envelope, with no `Table '...' doesn't exist` entry for `user_roles`/`user_status` in `logs/error.log`.
  - **Blocked by `fix-module-fatal-errors`**: `GET /api/v5/roles` cannot reach its query while `roles` constructs `BaseModel` with a `stdClass` payload (`TypeError`). The table-name fix is in place; this runtime check passes once that change lands. `GET /api/v5/users` (which does not depend on the roles constructor) is verified.
