# Proposal

## Why

The ORM data layer has no automated tests. All three existing tests cover routing, validation, and JWT; none exercise `DBGet`, `DBStore`, `DBUpdate`, or `DBDelete`. Every regression in SQL building, binding, or empty-result handling currently reaches production undetected, and the related robustness changes cannot be verified.

## What Changes

- The data layer accepts an injected connection/configuration so tests can point it at an in-memory SQLite database, without changing its default MySQL behavior.
- A reusable test harness provides a PDO connection, a minimal schema, and the constants the layer needs, so model tests can run without MySQL.
- Test coverage is added for the four data-layer classes over the portable paths: query building, filter operators, binding, insert/update/delete, and the recently defined robustness behaviors (error codes, empty collections, grouped counts, transaction rollback).
- The PHP image gains `pdo_sqlite` so the suite runs under `make test` in Docker.
- **BREAKING**: none. Injected configuration is optional; default behavior is unchanged.

## Capabilities

### New Capabilities
- `orm-test-coverage`: the automated coverage and test harness that verify the shared data-access layer against an in-memory database.

### Modified Capabilities
- Ninguna.

## Impact

- `app/core/model/conexion.php`, `get.php`, `store.php`, `update.php`, `delete.php` — add an optional injection seam for connection/config.
- `tests/` — new harness and test classes; `phpunit.xml` may gain `MYSQL_*`/`IDENTIFIER_UID` constants for the test environment.
- `docker/php-fpm/Dockerfile` — add the `pdo_sqlite` extension.
- No production behavior change; no schema change.
- Depends on the behaviors specified by `harden-orm-layer`; tests target that contract.
