# Design

## Context

See `proposal.md - Why`.

`BaseModel` builds `SELECT`, `INSERT`, and `UPDATE` column lists directly from `$moduleFields[alias]['field']`; a field whose `field` names a missing column produces invalid SQL and a `902000` error. `?search=` is also driven by the module's `search` list. `AppConfiguration` (`app/core/helpers/api_configuration.php`) already reads `$entry['value']`, confirming the column name. The table has no `public` column and the field is not consumed anywhere.

## Goals / Non-Goals

**Goals:**
- Make the `config` module's declared fields, search columns, and rules match the table.
- Preserve the module's alias-based naming approach.

**Non-Goals:**
- Changing the table or any data.
- Fixing `config::destroy()`; that is handled by a separate change.
- Adding new configuration capabilities such as visibility/public flag.

## Decisions

- **Map a `value` field to the `value` column, keeping the alias equal to the column name.** This matches the existing convention in other modules and what `AppConfiguration` already reads. Alternative rejected: renaming the alias to `data` and pointing its `field` at `value`, which would leave a misleading alias.
- **Remove `public` entirely** (field, rule, search entry) rather than leaving a phantom field. It does not exist in the schema and is unused. Alternative rejected: keeping it as a computed/default field, which would still produce invalid SQL unless mapped to an existing column, and there is none.
- **Drop `public` from `search`.** The remaining searchable columns are `name` and `slug`, both of which exist.

## Risks / Trade-offs

- A client may currently send or expect `data`/`public` in `/api/v5/config` payloads → Mitigation: the module is currently non-functional for those fields (it errors), so no working consumer can depend on them.
- `config` still has the unrelated `destroy()` fatal → Accepted; covered by a separate change. This change aligns fields but does not by itself make every `config` operation succeed.

## Migration Plan

- No data or schema migration. Deploy the single controller change.
- Rollback: revert the file; no persisted state to undo.

## Open Questions

- None blocking.
