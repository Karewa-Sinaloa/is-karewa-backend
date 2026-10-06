# Proposal

## Why

Four defects remain in the data-access layer and its query parsing. A filter value containing a colon (a time, URL, or hash) is split incorrectly and matches the wrong rows. A dead association guard on the `users` module references an undefined variable, so a delete association it intends to enforce can never work. The layer opens a new PDO on every helper call, which multiplies connections within a single request. And a duplicated `IN` branch in the filter builder is unreachable dead code that obscures the operator list.

## What Changes

- Filter parsing splits `operator:value` on the first colon only, so values containing colons are preserved.
- The `users` module's delete association guard is corrected so it targets the current record instead of an undefined variable.
- The layer caches its connection for the duration of a request instead of creating a new one per call, still honoring an injected connection.
- The duplicated `IN` branch in the filter builder is removed without changing the resulting SQL.
- **BREAKING**: none. All changes correct behavior that is currently broken or make no observable difference.

## Capabilities

### New Capabilities
- `orm-query-parsing`: how query-parameter filters are parsed into operators and values, including values that contain colons.

### Modified Capabilities
- Ninguna.

## Impact

- `app/core/bootstrap/midelware.php` — `getParamsFilters` colon parsing.
- `app/core/model/get.php` — duplicated `IN` branch.
- `app/core/model/conexion.php` — connection caching.
- `app/core/modules/users/controller.php` — association guard value.
- No schema or dependency changes.
- Interacts with `harden-orm-layer` (same files, connection seam) and `add-orm-tests` (connection injection); intended to land after `harden-orm-layer` and coordinate with its tests.
