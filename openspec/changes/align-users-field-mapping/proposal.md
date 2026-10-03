# Proposal

## Why

The `users` module declares fields that do not exist in its table. The schema
defines `recovery_date` (not `recovery_datetime`) and has no `phone_country_code`,
`photo`, or `email_verified` columns, yet `$moduleFields` maps them. Every
`users` read or write that selects those fields fails with database error
`902000` (`Unknown column 'u.recovery_datetime'`). The `roles` module also fails
on construction: it passes `$_payload` (a `stdClass` or `null`) to
`BaseModel::__construct(array $additionalData = [])`, causing a `TypeError`
before any query runs.

## What Changes

- The `users` module maps the recovery-date field to the existing `recovery_date`
  column instead of the non-existent `recovery_datetime`.
- The `phone_country_code`, `photo`, and `email_verified` fields are removed from
  `$moduleFields` and their validation rules, since the columns do not exist.
  The unmapped existing columns `middle_name` and `second_last_name` are added to
  `$moduleFields` so the module reflects the table.
- Observable behavior: `GET /api/v5/users` returns the standard success envelope
  instead of HTTP 500 with database error `902000`.
- No route, response-code, prefix, or schema changes.
- **BREAKING**: none for the endpoints; the `phone_country_code`, `photo`, and
  `email_verified` fields are no longer exposed or accepted by the `users`
  module because they were never backed by the table.

## Capabilities

### New Capabilities
- `user-module-fields`: the field map, searchable columns, and validation rules
  the `users` module exposes over its table.

### Modified Capabilities
- Ninguna.

## Impact

- `app/core/modules/users/controller.php` — `$moduleFields` and `$rules`.
- Affects the `/api/v5/users` endpoint.
- No schema, dependency, or public-contract changes; the table already has the
  `recovery_date`, `middle_name`, and `second_last_name` columns.
