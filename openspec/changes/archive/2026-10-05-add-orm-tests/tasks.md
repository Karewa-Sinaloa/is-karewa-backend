# Tasks

## 1. Injection seam and test bootstrap

- [x] 1.1 In `app/core/model/conexion.php`, add a static, resettable connection/prefix override to `DB` (for example `setConnection`, `setPrefix`, `reset`) and have `connection()` return the override when set, otherwise the current MySQL behavior; route the prefix through the same accessor. Verify `php -l` passes and no call site changes signature.
- [x] 1.2 Add a test-support base case `tests/Support/OrTestCase.php` that creates a fresh in-memory SQLite connection, sets a known schema, applies the connection/prefix overrides, stubs `error_logs()`, and resets overrides in `tearDown()`. Verify a trivial test using it can query the in-memory DB.
- [x] 1.3 Add any missing constants (`MYSQL_*`, `IDENTIFIER_UID`) to `phpunit.xml` as test values, matching the existing const pattern. Verify `./vendor/bin/phpunit` still bootstraps without undefined-constant warnings.
- [x] 1.4 Add `tests/OrHarnessTest.php` asserting each test starts from a clean database (isolation), that the injected connection is used, and that the prefix is empty. Verify `./vendor/bin/phpunit tests/OrHarnessTest.php` passes.

## 2. Data-layer unit tests

- [x] 2.1 Add `tests/OrGetTest.php` for `DBGet`: field selection, filters/operators, joins, ordering, `list` versus single-record, and parameter binding against seeded rows. Verify `./vendor/bin/phpunit tests/OrGetTest.php` passes.
- [x] 2.2 Add `tests/OrWriteTest.php` for `DBStore`, `DBUpdate`, and `DBDelete`: insert returns the id, update reports affected rows, delete reports affected rows, and prefixed table names resolve to the test table. Verify `./vendor/bin/phpunit tests/OrWriteTest.php` passes.
- [x] 2.3 Add `tests/OrErrorCodeTest.php` (or extend the one from the robustness change) asserting a domain error keeps its code and a SQL failure yields the database code. Verify `./vendor/bin/phpunit tests/OrErrorCodeTest.php` passes.
- [x] 2.4 Add `tests/OrGroupedCountTest.php` and `tests/OrTransactionTest.php` asserting grouped count equals the number of groups and a failed multi-step write rolls back. Verify both files pass.

## 3. Environment

- [x] 3.1 Add the `pdo_sqlite` extension to `docker/php-fpm/Dockerfile` alongside the existing extensions. Verify by rebuilding (`docker compose build php`) and running `php -m` in the container to confirm `pdo_sqlite` is listed.
- [x] 3.2 Document how to run the ORM tests in `orm_wiki.md`, including the in-memory database assumption and the Docker image requirement. Verify the documented commands match `Makefile` targets.

## 4. Integration verification

- [x] 4.1 Run the full suite with `./vendor/bin/phpunit` (or `make test`) and confirm all tests, including the new data-layer tests, pass.
- [x] 4.2 Confirm the existing tests still pass unchanged and that injecting no connection leaves production behavior identical (no MySQL dependency introduced into the default path).
