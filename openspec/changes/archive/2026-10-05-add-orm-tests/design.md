# Design

## Context

See `proposal.md - Why`.

Today `DB::connection()` builds a `new PDO` on every call from the `MYSQL_*` constants and returns it; the four data classes each call it directly. Tests bootstrap `vendor/autoload.php` and load files with `require_once`, defining `error_logs()` as a global stub and setting `CORE_PATH`. `AppException` is global and calls `error_logs()` in `__destruct`. The Docker image installs `pdo_mysql`/`mysqli` only; the host has `pdo_sqlite`. The related `harden-orm-layer` change defines the behaviors these tests must target.

## Goals / Non-Goals

**Goals:**
- Add the smallest injection seam that lets tests route the layer to an in-memory SQLite connection.
- Keep production behavior byte-for-byte equivalent when nothing is injected.
- Make the suite green under `./vendor/bin/phpunit` and `make test`.

**Non-Goals:**
- Building a general DI container or connection pooling.
- Testing MySQL-specific behavior (for example `LIMIT :inf, :max`) against SQLite; those stay for the Docker integration check.
- Replacing the existing `require_once`-based test loading with PSR-4 autoloading.
- Re-specifying the robustness behaviors; they belong to `harden-orm-layer`.

## Decisions

- **Inject through a static, resettable override on `DB`.** Add something like `DB::setConnection(PDO $pdo)` / `DB::setPrefix(string $prefix)` and a `DB::reset()` for teardown, with `connection()` returning the override when present. Alternative rejected: constructor injection across every static class, which would touch every module and break the existing static call sites.
- **Drive the prefix through the same seam instead of a constant.** The layer uses `MYSQL_PREFIX`; tests need it empty. Expose it via `DB` so tests can set `''` without redefining a constant. Alternative rejected: defining `MYSQL_PREFIX` in the test bootstrap, which would conflict if `base.php` is ever loaded.
- **A base test case builds the schema.** Create `tests/Support/OrTestCase.php` with setup that creates a fresh in-memory SQLite connection, sets a known schema (including a groupable column), applies the connection/prefix overrides, and resets them in teardown. Alternative rejected: a per-test SQLite file, which is slower and leaves artifacts.
- **Require the model files explicitly and define the needed constants in the bootstrap.** Because autoloading does not cover these classes and `MIDDLEWARE`-time constants may be undefined, define `MYSQL_*`/`IDENTIFIER_UID` values in `phpunit.xml` and stub `error_logs()`, matching the pattern of the existing tests.
- **Add `pdo_sqlite` to the image.** It is a one-line extension addition; without it `make test` cannot run the new tests. Alternative rejected: gating tests on extension presence, which turns coverage into a silent skip.

## Risks / Trade-offs

- A static override can leak between tests and mask ordering bugs → Mitigation: reset in `tearDown()` and assert isolation in at least one test.
- SQLite accepts SQL that MySQL rejects and vice versa → Mitigation: keep behavioral tests on portable paths and verify MySQL-specific paths only in the Docker integration check.
- Defining `MYSQL_*` in `phpunit.xml` can drift from production defaults → Mitigation: tests only rely on the value being defined, and the layer reads the prefix through the seam.
- Adding `pdo_sqlite` grows the image slightly → Accepted; the coverage gain outweighs it.

## Migration Plan

- Deploy the injection seam with the tests together; the seam is inert unless called.
- Update `docker/php-fpm/Dockerfile` and rebuild the image; run `make test`.
- Rollback: remove the test files and the seam; no production state changes.

## Open Questions

- None blocking.
