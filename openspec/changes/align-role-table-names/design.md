# Design

## Context

See `proposal.md - Why`.

`BaseModel` and the `DB*` layer prepend the configured `MYSQL_PREFIX` to whatever base table name a module declares; the prefix is applied exactly once per table name. Module `$get_params['table']` may carry a trailing alias (`user_roles r`) and joins carry the same form (`user_status s`). Validation's `exist:table:column` rule also resolves its table through `DBGet`, which prefixes it. The schema defines `dev_roles` / `dev_users_status`, so the base names are `roles` and `users_status`.

## Goals / Non-Goals

**Goals:**
- Make the `roles` and `users` modules use the base names `roles` and `users_status` everywhere they resolve a table.
- Keep the prefix behavior unchanged and environment-agnostic.

**Non-Goals:**
- Renaming or regenerating any database table or dump.
- Fixing the `config` field mapping (`data`/`public` vs `value`).
- Reworking how the ORM applies prefixes.

## Decisions

- **Correct the base names in the code, not the schema.** The schema already matches the canonical base names once the prefix is stripped (`dev_roles` → `roles`), and it is uploaded manually. Changing the code is the smaller, safer fix. Alternative rejected: renaming tables to `user_roles`/`user_status`, which would also require regenerating dumps and production schema for no functional gain.
- **Preserve the alias form when editing.** Joins stay as `roles r` and `users_status s` so the `s.name`/`r.name` field references keep resolving. Alternative rejected: dropping aliases, which would break the qualified column references in `$moduleFields`.
- **Update validation rule tables to match.** `exist:user_roles:id` and `exist:user_status:id` change to `exist:roles:id` and `exist:users_status:id`, since `exist` also goes through the prefixing layer.

## Risks / Trade-offs

- A module other than `users`/`roles` may reference the old names → Mitigation: a full-codebase search confirms only these two modules reference `user_roles`/`user_status`.
- The `roles` module still has an unrelated constructor type error (`stdClass` to `array`) → Accepted; that is covered by a separate change. This change aligns names but does not by itself make `/api/v5/roles` work end-to-end.
- Production schema might not match the dump base names → Mitigation: the spec is written in terms of base names, so it holds for `krw_roles`/`krw_users_status` as well; if production differs, that is an operational data issue, not a code change.

## Migration Plan

- No data or schema migration. Deploy the two controller changes together.
- Rollback: revert both files; no persisted state to undo.

## Open Questions

- None blocking. The `config` field-mapping mismatch is deferred to a separate change.
