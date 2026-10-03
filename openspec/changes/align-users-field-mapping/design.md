# Design

## Context

The `users` module builds its SELECT list from `$moduleFields`, translating each
`field` value directly into a column reference. The table `{prefix}users` is the
source of truth; the module must not declare fields that are not columns. The
database prefix is applied once by the ORM, so only the base column names matter
here.

## Goals / Non-Goals

- Goal: `GET /api/v5/users` succeeds over a prefixed schema.
- Non-goal: changing the users table schema. Fields without columns are dropped
  from the module, not added to the table.
- Non-goal: the `config` module's field mapping (`value` vs `data`), tracked
  separately by `align-config-field-mapping`.
- Non-goal: the `roles` constructor `TypeError`, tracked separately by
  `fix-module-fatal-errors`.

## Decisions

- **Map `recovery_date` to the `recovery_date` column.** The field name and the
  column agree; only the referenced column was wrong (`recovery_datetime`).
  Alternative rejected: renaming the column, which would change the schema.
- **Remove `phone_country_code`, `photo`, and `email_verified` from the module.**
  They have no backing columns and are not part of the current schema. Alternative
  rejected: adding columns, which expands scope beyond fixing the mismatch and is
  a schema change. If these are needed later, they belong in a dedicated
  migration change.
- **Add `middle_name` and `second_last_name` to `$moduleFields`.** They exist in
  the table but were unmapped; adding them makes the module reflect the table and
  lets list/show return complete records. Alternative rejected: leaving them
  unmapped, which silently hides real data.
## Risks / Trade-offs

- Removing fields is observable: clients sending `phone_country_code`, `photo`,
  or `email_verified` will no longer have them accepted. Mitigation: they were
  never persisted (no columns), so no working behavior is lost; document the
  change.
- Adding `middle_name`/`second_last_name` grows list/show responses. Mitigation:
  they are real columns and are expected in a users payload.

## Migration Plan

- Code-only change; no data migration.
- Rollback: revert the two controller files.

## Open Questions

- None.
