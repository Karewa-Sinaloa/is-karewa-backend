# Tasks

## 1. Colon-safe filter parsing

- [x] 1.1 In `app/core/bootstrap/midelware.php`, change `getParamsFilters()` to split on the first colon only (`explode(':', $v, 2)`), keeping the remainder intact as the value and falling back to equality when no known operator precedes it. Verify `php -l` passes and `rg "explode\(':', \$v\)" app/core/bootstrap/midelware.php` no longer matches.
- [x] 1.2 Add `tests/OrFilterParsingTest.php` asserting a plain value, `eq:42`, and a colon-containing value (for example `lk:10:30`) each produce the expected filter. Verify `./vendor/bin/phpunit tests/OrFilterParsingTest.php` passes.
- [x] 1.3 Update the filter examples in `orm_wiki.md` to note that values may contain colons. Verify the documented example matches the implemented parsing.

## 2. Delete association guard

- [x] 2.1 In `app/core/modules/users/controller.php`, replace `'value' => $id` in `$table_assoc` with the current record's identifier so the guard compares correctly. Verify `php -l` passes and the LSP "Undefined variable $id" diagnostic at that line is gone.
- [x] 2.2 Add `tests/OrAssociationGuardTest.php` asserting the guard rejects a delete when associated rows exist and allows it when none do (using the in-memory harness). Verify `./vendor/bin/phpunit tests/OrAssociationGuardTest.php` passes.

## 3. Connection reuse

- [x] 3.1 In `app/core/model/conexion.php`, cache the connection on `DB` for the request and return it on subsequent calls, while returning an injected connection first. Verify `php -l` passes and that two consecutive calls with no injection return the same instance.
- [x] 3.2 Add `tests/OrConnectionTest.php` asserting repeated calls reuse one connection and an injected connection takes precedence. Verify `./vendor/bin/phpunit tests/OrConnectionTest.php` passes.

## 4. Duplicate operator branch

- [x] 4.1 In `app/core/model/get.php`, remove the second, unreachable `elseif ($value[2] == 'IN')` branch from `get_filters()`. Verify `php -l` passes and a captured SQL string for an `IN` filter is identical before and after (assert in the test).
- [x] 4.2 Extend `tests/OrGetTest.php` with an `IN`-operator case asserting the produced SQL and results. Verify `./vendor/bin/phpunit tests/OrGetTest.php` passes.

## 5. Integration verification

- [x] 5.1 Run the full suite with `./vendor/bin/phpunit` (or `make test`) and confirm all tests pass.
- [x] 5.2 With `make up`, exercise a filter with a colon-containing value and a delete on an associated record, confirming correct matching and the association rejection in `logs/error.log`.
