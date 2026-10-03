# Proposal

## Why

Two core modules fail with unhandled PHP errors instead of returning the API's standard response envelope. `roles` passes a `stdClass` to a constructor parameter typed `array`, raising a `TypeError` on every request. `config`'s `destroy()` reads an undefined `$id`, reads an undeclared property, and calls a non-static method statically, raising an `Error` on every delete. Both endpoints are therefore unusable and leak fatals rather than documented error codes.

## What Changes

- `roles` constructs without a type error by no longer passing the request payload as the constructor's additional-data argument.
- `config` adopts the standard CRUD behavior for its declared methods, so its `destroy()` uses the model's normal delete flow.
- The `config` association guard is removed: its `$table_assoc` was copied from `roles` (checking `users.role_id`) and does not describe a real relationship for configuration rows.
- Observable behavior: `/api/v5/roles` and `/api/v5/config` respond with the standard JSON envelope and documented codes instead of terminating with an unhandled PHP error.
- No route, payload, response-code, schema, or dependency changes.
- **BREAKING**: none.

Explicit non-goals: the schema drift where `roles`, `users` reference `user_roles`/`user_status` while the dumps define `dev_roles`/`dev_users_status`, and where `config.moduleFields` maps `data`/`public` while `dev_config` only has `value`. Those are separate defects; requests can still surface them as a standard database error (`902000`) after this change.

## Capabilities

### New Capabilities
- `core-module-crud`: the request handling and CRUD behavior of the built-in `roles` and `config` modules.

### Modified Capabilities
- Ninguna.

## Impact

- `app/core/modules/roles/controller.php` — constructor.
- `app/core/modules/config/controller.php` — CRUD methods and delete behavior.
- Affects the `/api/v5/roles` and `/api/v5/config` endpoints only.
- No schema, configuration, dependency, or public-contract changes.
