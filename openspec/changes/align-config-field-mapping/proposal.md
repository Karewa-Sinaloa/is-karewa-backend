# Proposal

## Why

The `config` module's field map does not match its table. The schema defines `id`, `name`, `slug`, and `value`, but the module declares fields `data` and `public`, which do not exist. Every `config` read/write that includes those fields fails with database error `902000`, and `?search=` on `config` always fails because `public` is declared as a searchable column. The rest of the codebase already reads the `value` column, so the module alone is out of line.

## What Changes

- The `config` module maps a `value` field to the `value` column instead of `data` to a non-existent `data` column.
- The `public` field is removed from `$moduleFields`, its validation rule is removed, and it is removed from the searchable columns.
- Observable behavior: `/api/v5/config` reads and writes the `value` column correctly, and `?search=` no longer fails.
- No route, response-code, prefix, or schema changes.
- **BREAKING**: none.

## Capabilities

### New Capabilities
- `config-module-fields`: the field map, searchable columns, and validation rules the `config` module exposes over its table.

### Modified Capabilities
- Ninguna.

## Impact

- `app/core/modules/config/controller.php` — `$moduleFields`, `$get_params['search']`, `$rules`.
- Affects the `/api/v5/config` endpoint.
- No schema or dependency changes; the table already has the required `value` column.
