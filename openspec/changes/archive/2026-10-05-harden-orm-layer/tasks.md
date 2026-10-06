# Tasks

## 1. Error propagation and codes

- [x] 1.1 In `get.php`, `store.php`, `update.php`, `delete.php`, change the `catch(\Exception $e)` handlers so an `AppException` is rethrown unchanged and only other exceptions are wrapped in the new database code. Verify `php -l` passes for all four files and diff the handlers to confirm no unconditional `902000` wrap remains.
- [x] 1.2 Add the distinct database-failure code to `app/core/config/api_codes.yml` (mapped to HTTP 500) and keep the existing generic code. Verify `ApiResponse::Set()` with the new code returns its documented status.
- [x] 1.3 Add `tests/OrErrorCodeTest.php` (using `pdo_sqlite`, prefix `''`) asserting a domain `AppException` keeps its code and a forced SQL failure yields the database code. Verify `./vendor/bin/phpunit tests/OrErrorCodeTest.php` passes.

## 2. Empty collections

- [x] 2.1 In `midelware.php`, make `BaseModel::get()` return the success envelope with `data: []` when the request is a list (`$result_type` is `list`) and `Get()` returned no rows, while keeping the not-found path for single-record requests. Verify `php -l` passes and the show/list branches are clearly separated.
- [x] 2.2 Add `tests/OrEmptyCollectionTest.php` asserting an empty `index` returns success with `[]` and an empty `show` still returns not-found. Verify `./vendor/bin/phpunit tests/OrEmptyCollectionTest.php` passes.

## 3. Grouped counts

- [x] 3.1 In `get.php`, change the `count` case to compute the grouped total correctly (subquery over the existing `GROUP BY` builder) and adjust `get_bind_data` accordingly. Verify `php -l` passes and the built SQL for a grouped count is the subquery form.
- [x] 3.2 In `midelware.php`, replace the pagination `group_by` workaround (list-and-count) with the corrected count query. Verify the pagination totals code path no longer lists rows to count them.
- [x] 3.3 Add `tests/OrGroupedCountTest.php` asserting a grouped count equals the number of distinct groups and an ungrouped count equals total rows. Verify `./vendor/bin/phpunit tests/OrGroupedCountTest.php` passes.

## 4. Transaction boundaries

- [x] 4.1 In `conexion.php`, add begin/commit/rollback helpers on `DB` that use the connection returned by `connection()`. Verify `php -l` passes.
- [x] 4.2 In `delete.php`, wrap the multi-statement path (association check + delete) in a transaction and roll back on any failure so no partial change persists. Verify `php -l` passes and every return path is preceded by a commit or rollback.
- [x] 4.3 Document transaction usage in `orm_wiki.md` and verify the documented example matches the implemented helper names.
- [x] 4.4 Add `tests/OrTransactionTest.php` asserting a failure inside a multi-step write rolls back all statements and a success commits them. Verify `./vendor/bin/phpunit tests/OrTransactionTest.php` passes.

## 5. Integration verification

- [x] 5.1 Run the full suite with `./vendor/bin/phpunit` (or `make test`) and confirm all tests pass.
- [x] 5.2 With `make up` and prefix `dev_`, exercise a list endpoint with no matches (expect `200` with `data: []`), a missing single record (expect `404`), and a grouped count via `?embed=pagination&groupby=...` (expect group totals). Confirm no new fatal appears in `logs/error.log`.
