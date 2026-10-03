# Tasks

## 1. Fix the roles constructor fatal

- [ ] 1.1 In `app/core/modules/roles/controller.php`, replace `parent::__construct($_payload)` with `parent::__construct()` (the payload is already loaded by the base constructor and `Roles` derives no fields). Verify `php -l app/core/modules/roles/controller.php` passes and the `Expected type 'array'. Found 'stdClass'` diagnostic at that line is gone.
- [ ] 1.2 Add `tests/ModuleFatalErrorsTest.php` that requires `CORE_PATH . 'bootstrap/midelware.php'` and the roles controller, sets `$_GET['id'] = null`, and asserts `new Roles()` succeeds and is a `App\Model\BaseModel` instance. Verify `./vendor/bin/phpunit tests/ModuleFatalErrorsTest.php` passes (the test raises a `TypeError` before this group's change).

## 2. Fix the config delete fatal

- [ ] 2.1 In `app/core/modules/config/controller.php`, add `use Crud;` and remove the five hand-written `show`/`index`/`store`/`update`/`destroy` methods so the module uses the standard CRUD behavior (no association guard). Verify `php -l app/core/modules/config/controller.php` passes and the `$id` / `$db_table` diagnostics are gone.
- [ ] 2.2 Extend `tests/ModuleFatalErrorsTest.php` to require the config controller and assert `AppConfig` uses the `Crud` trait and exposes a callable `destroy` method (via `class_uses` and `method_exists`). Verify `./vendor/bin/phpunit tests/ModuleFatalErrorsTest.php` passes.

## 3. Integration verification

- [ ] 3.1 Run the full suite with `./vendor/bin/phpunit` (or `make test`) and confirm all tests pass, including the existing `RoutesTest`, `ValidationTest`, and `JWTKeyEncodeTest`.
- [ ] 3.2 With `make up` and an authorized token, call `/api/v5/roles` and `DELETE /api/v5/config/{id}` and confirm each returns a standard JSON envelope with a documented `code` and HTTP status. Verify `logs/error.log` records no unhandled `TypeError` or `Error` for those requests.
