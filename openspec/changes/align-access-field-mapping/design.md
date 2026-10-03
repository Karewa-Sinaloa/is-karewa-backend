# Design

## Context

The `access` module writes and reads user columns directly (it does not go
through a `$moduleFields`-driven SELECT for the recovery/reset paths). The users
table is the source of truth: it has `recovery_code` and `recovery_date`, and no
`email_verified` column. The database prefix is applied once by the ORM, so only
the base column names matter.

## Goals / Non-Goals

- Goal: `POST /api/v5/access/recovery` and `POST /api/v5/access/reset` work
  against the current users schema.
- Non-goal: changing the users table schema, or adding an email-verification
  column. The `email_verified` write is dropped, not migrated.
- Non-goal: the `users` module field map and the `roles`/`users_status` table
  names, tracked by `align-users-field-mapping` and `align-role-table-names`.

## Decisions

- **Use `recovery_date` everywhere `recovery_datetime` was used.** The field
  name, the mapped column, the update field list, the reset SELECT list, and the
  expiration lookup all change to the real column name. Alternative rejected:
  renaming the column, which would change the schema.
- **Drop the `email_verified` write from reset.** The column does not exist.
  Setting it back to a "no-op with a different existing column" would be
  inventing behavior; the safest correct action is to not write it. Alternative
  rejected: writing to `status_id` or another column, which changes semantics.
- **Keep the `recovery_date` field mapped in `$moduleFields` for consistency.**
  Even though the recovery/reset paths use explicit field lists, the mapping is
  corrected so any code path that uses it resolves to a real column.

## Risks / Trade-offs

- Dropping the `email_verified` write means clients no longer receive that flag
  change. Mitigation: it was never persisted, so no working behavior is lost.
- Recovery/reset depend on the users table having `recovery_date`; this is
  already the case.

## Migration Plan

- Code-only change; no data migration.
- Rollback: revert `app/core/modules/access/local_login.php`.

## Open Questions

- Whether an email-verification feature is intended. If so, it needs its own
  change with a schema migration; out of scope here.
