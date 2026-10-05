# Proposal

## Why

The `access` module (login, recovery, and reset flows) references user columns
that do not exist. It uses `recovery_datetime` where the schema defines
`recovery_date`, and writes an `email_verified` column that the users table does
not have. The `recovery` flow fails on `DBUpdate` with `Unknown column
'recovery_datetime'`, and the `reset` flow fails on both `recovery_datetime` and
`email_verified`, so account recovery and password reset are unusable with
database error `902000` or `909000`.

## What Changes

- The `access` module maps its recovery-date field to the existing
  `recovery_date` column instead of the non-existent `recovery_datetime`.
- The recovery write sets `recovery_date` (not `recovery_datetime`).
- The reset read selects `recovery_date` (not `recovery_datetime`) and reads the
  expiration from that key.
- The reset write clears `recovery_date` and stops writing the non-existent
  `email_verified` column; completing a reset no longer attempts to set a column
  that does not exist.
- Observable behavior: `POST /api/v5/access/recovery` and
  `POST /api/v5/access/reset` complete against the current schema instead of
  failing with `Unknown column`.
- No route, response-code, prefix, or schema changes.
- **BREAKING**: none for the endpoints; the `email_verified` flag is no longer
  written because it was never a column.

## Capabilities

### New Capabilities
- `access-recovery-fields`: the user columns the `access` module reads and writes
  for login, account recovery, and password reset.

### Modified Capabilities
- Ninguna.

## Impact

- `app/core/modules/access/local_login.php` — `$moduleFields` and the `Recovery`/
  `Reset` query field lists.
- Affects the `/api/v5/access/recovery` and `/api/v5/access/reset` endpoints.
- No schema, dependency, or public-contract changes; the users table already has
  the `recovery_date` column.
