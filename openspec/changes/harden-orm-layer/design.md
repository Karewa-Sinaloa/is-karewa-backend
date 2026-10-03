# Design

## Context

See `proposal.md - Why`.

The layer is four static classes over one `DB::connection()` factory: `DBGet` (`get.php`), `DBStore` (`store.php`), `DBUpdate` (`update.php`), `DBDelete` (`delete.php`). Each opens its own `new PDO`, prepares, binds, and executes. `AppException` carries a code via `errorCode()`. `BaseModel` in `midelware.php` calls these classes and maps results to `ApiResponse::Set()`. `api_codes.yml` maps symbolic codes to HTTP statuses. There are no existing tests for the layer; `phpunit.xml` defines the constants tests need and `pdo_sqlite` is available in the PHP image.

## Goals / Non-Goals

**Goals:**
- Make each of the four behaviors in the spec testable at the data-layer boundary.
- Keep the existing call signatures and the prefix convention (`MYSQL_PREFIX` applied once, in the layer).
- Preserve the response envelope shape for all non-empty results.

**Non-Goals:**
- Introducing a query builder, an ORM entity layer, or a DI container.
- Sharing a single PDO connection across requests (connection reuse is a separate concern).
- Changing `api_codes.yml` beyond adding at most one database-failure code.
- Fixing module-specific bugs already covered by other changes.

## Decisions

- **Propagate existing codes; add one distinct database code.** Catch `AppException` and rethrow it as-is; only wrap `PDOException`/`\Exception` from prepare/execute in the new database code. Alternative rejected: mapping specific SQLSTATEs to distinct codes, which is premature given the catalog's size.
- **Detect empty collections by requested action, not by a single `NULL`.** `DBGet::Get()` returns `null` for "no rows"; `BaseModel::get()` already knows whether the request is a list (`$result_type`) or a single record. Return an empty array for list requests and keep the not-found path for single-record requests. Alternative rejected: changing `Get()` to always return `[]`, which would erase the show/not-found distinction.
- **Honour `GROUP BY` in `count` via `COUNT(DISTINCT ...)` on the grouped columns, or a subquery.** Simplest correct approach: `SELECT COUNT(*) FROM (SELECT 1 FROM table ... GROUP BY ...) t`; it reuses the existing filter/join/group builders and matches the list query exactly. Alternative rejected: `COUNT(DISTINCT concat(cols))`, which mishandles `NULL` and separators.
- **Transaction at the layer boundary.** Have `DBDelete::delete()` begin a transaction when it performs more than one statement (association check + delete) and commit/roll back; expose begin/commit/rollback helpers the controllers can reuse for module-level multi-step writes. Alternative rejected: transactions only in controllers, which leaves the layer's own multi-step path non-atomic.
- **SQLite for tests.** The layer builds portable SQL for the tested paths; tests run against an in-memory `pdo_sqlite` with the prefix set to `''`. Alternative rejected: requiring MySQL, which the existing suite does not assume.

## Risks / Trade-offs

- Returning `200` with `[]` for empty lists changes an externally observable status → Mitigation: mark it **BREAKING** in the proposal and cover it with an explicit test; single-record `404` is preserved.
- A subquery-based grouped count may differ from `BaseModel::get`'s existing list-and-count workaround → Mitigation: replace that workaround with the corrected count and assert both paths agree in a test.
- Transactions with `ATTR_PERSISTENT` connections can leak state between requests → Mitigation: always commit or roll back in a `finally`, and do not leave a transaction open on any path.
- Portable SQL versus MySQL specifics (`LIMIT :inf, :max`) → Mitigation: keep behavioral tests to the paths that are portable and leave MySQL-only paths to the Docker integration check.

## Migration Plan

- Deploy the layer and the `BaseModel::get` change together; add the new code to `api_codes.yml` if used.
- Rollback: revert the model files and the helper; no persisted state to undo (transactions make rollback safe).
- Clients see the empty-list status change immediately; document it in the API notes.

## Open Questions

- None blocking.
