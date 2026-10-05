# Design

## Context

See `proposal.md - Why`.

The ORM is layered: modules declare `$table_assoc`; `BaseModel::delete()` forwards it to `DBDelete::delete()`; `DBDelete::is_asociated()` builds a `DBGet::Get(..., 'count')` call to test for referencing rows. `DBGet::Get()` is the single component that prepends the configured `MYSQL_PREFIX` to a table name. `DBDelete::is_asociated()` currently prefixes the related table before handing it to `DBGet`, producing a doubly-prefixed identifier (`dev_dev_*` under the configured `dev_` prefix).

The `users` module declares an association on `customers.user_id` but supplies `$id`, which is never defined in its constructor, so the comparison value is `null`.

## Goals / Non-Goals

**Goals:**
- Make the association check issue a valid query for the configured (possibly non-empty) prefix.
- Make the `users` association check compare against the id of the record being deleted.
- Keep the change minimal and confined to the guard path.

**Non-Goals:**
- Refactoring the ORM's table-prefixing contract or introducing a shared identifier helper.
- Fixing `config::destroy()` (static call to a non-static method, undeclared property) or the `roles` constructor type error; these keep their own modules broken independently of the guard.
- Adding transactions or reworking the delete flow.

## Decisions

- **Remove the prefix from `DBDelete::is_asociated()` rather than defend inside `DBGet`.** `DBGet` is already the owner of prefixing and every other caller passes bare table names. Making `DBGet` tolerate already-prefixed names would hide the caller's mistake and add a heuristic. Alternative rejected: strip a prefix if present in `DBGet`.
- **Derive the `users` association value from the request entry id (`$_GET['id']`).** The `roles` module already uses `$_GET['id']` for the same purpose, so this follows the incumbent pattern. `$this->entryId` is only populated later, inside the CRUD flow's `init()`, while `$table_assoc` is built in the constructor, so `$_GET['id']` is the value available at that point.
- **Leave both edits where the defect is.** No new abstraction is introduced; the fix is two localized changes.

## Risks / Trade-offs

- Another caller may pass an already-prefixed table to `DBGet` → Mitigation: grep confirms `is_asociated()` is the only place that prefixed before calling `DBGet`; all other call sites pass bare names.
- Changing the `users` value source could alter delete permissions → Mitigation: `REQUEST_TYPE` enforcement already requires `?id` for `destroy`, so `$_GET['id']` is present and numeric on that path; behavior for non-delete requests is unchanged.
- The guard still depends on each module populating `$table_assoc` correctly → Accepted; the spec requires correctness per module, and `config` remains out of scope.

## Migration Plan

- No data or schema migration. Deploy the two code changes together.
- Rollback: revert the two lines; there is no persisted state to undo.

## Open Questions

- None blocking. Whether to fix the `config` and `roles` defects is deferred to a separate change.
