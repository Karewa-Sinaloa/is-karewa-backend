# Proposal

## Why

The pre-delete association guard in the ORM is broken, so the API either fails on legitimate deletes or silently allows deleting rows that are still referenced. `DBDelete::is_asociated()` applies `MYSQL_PREFIX` to the related table and then passes it to `DBGet::Get()`, which applies the prefix again. With the configured prefix `dev_` this produces queries against `dev_dev_*` tables, which fail with database error `902000`. On top of that, the `users` module builds its association entry with an undefined `$id`, so even once the table name is correct the guard would check `user_id = NULL` and never protect the user.

## What Changes

- `DBDelete::is_asociated()` stops applying `MYSQL_PREFIX` itself and passes the raw table name to `DBGet::Get()`, which owns prefixing.
- The `users` module sources the association key value from the resolved request entry id instead of the undefined `$id`.
- Observable behavior: `DELETE` on a row referenced by an associated table returns `902002` (`APP_ENTRY_RELATIONSHIP`, HTTP 409) consistently when a prefix is configured; an unreferenced row deletes normally.
- No change to the public API surface, route names, payloads, or response codes.
- **BREAKING**: none.

Related defects found on the same code path but explicitly out of scope: `config::destroy()` calls a non-static method statically and reads an undeclared property, and the `roles` controller passes an object to a constructor typed `array`.

## Capabilities

### New Capabilities
- `delete-association-guard`: the pre-delete referential check that blocks deletion of a row referenced by an associated table.

### Modified Capabilities
- Ninguna.

## Impact

- `app/core/model/delete.php` — `is_asociated()` table naming.
- `app/core/modules/users/controller.php` — `$table_assoc` key value.
- Affects the delete path of every module that declares `$table_assoc` (currently `users` and `roles`; `config` remains blocked by its own separate defect).
- No schema, configuration, dependency, or public-contract changes.
