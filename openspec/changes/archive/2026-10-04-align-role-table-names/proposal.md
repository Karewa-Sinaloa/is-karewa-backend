# Proposal

## Why

The `users` and `roles` modules reference database tables `user_roles` and `user_status`, but the schema defines the base names `roles` and `users_status`. The database prefix is an environment variable applied once by the ORM, so the base name must be identical in every environment; with the current code, both modules resolve to tables that do not exist (`dev_user_roles`, `krw_user_roles`, or `user_roles`) and fail with database error `902000`.

## What Changes

- The `roles` module uses the base table name `roles` instead of `user_roles`.
- The `users` module joins `roles` and `users_status` instead of `user_roles` and `user_status`.
- The `users` validation rules reference `roles` and `users_status` instead of `user_roles` and `user_status`.
- Observable behavior: `/api/v5/roles` and `/api/v5/users` resolve their tables correctly under any configured prefix and stop returning `902000` for table-not-found.
- No route, payload, response-code, or prefix-configuration changes.
- **BREAKING**: none.

Explicit non-goal: the `config` module's field mapping (`data`/`public` vs the `value` column in the schema) is a separate mismatch and is out of scope.

## Capabilities

### New Capabilities
- `role-table-naming`: the base table names the `roles` and `users` modules use for role and user-status data, consistent across database prefixes.

### Modified Capabilities
- Ninguna.

## Impact

- `app/core/modules/roles/controller.php` — primary table name.
- `app/core/modules/users/controller.php` — join table names and validation rules.
- Affects the `/api/v5/roles` and `/api/v5/users` endpoints.
- No schema, dependency, or public-contract changes; the dumps already define `roles` and `users_status` under their prefix.
