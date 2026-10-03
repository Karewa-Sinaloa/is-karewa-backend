# Design

## Context

See proposal.md - Why. The Facebook surface is dead code except for one persisted column: there is no OAuth callback or graph call anywhere in the backend. `facebook_id` is only referenced by the users module mappings/rules, the two SQL schema snapshots, one test constants list, and generated OpenAPI. Config defines `FB_API_ID`/`FB_API_SECRET`/`FB_API_VERSION` in `app/core/config/base.php` but nothing consumes them.

## Goals / Non-Goals

**Goals:**
- Remove all Facebook/social-login references so the only authentication path is local email + password.
- Drop the unused `dev_users.facebook_id` column through a versioned migration.
- Keep the users module, its tests, and generated API docs consistent with the new schema.

**Non-Goals:**
- Adding or changing the email + password flow itself (it already exists in `AppAccess::Login()`).
- Auditing the separate Vue frontend repository for social-login UI.
- Adding any new provider-agnostic "social login" abstraction.

## Decisions

- **Full column drop over deprecation.** Rationale: the decision is that social login must not exist, and leaving the column invites re-use and keeps a stale API field. Alternative considered: mark the field `saved => false` / hide from responses; rejected because it leaves the schema and docs ambiguous and still ships the identifier.
- **Versioned SQL migration over editing only the schema snapshots.** Rationale: `resources/migrations/` is the established pattern (`rate_limits.sql`); a new `drop_users_facebook_id.sql` is replayable on existing databases. The `resources/karewa_dev.sql` and `app/karewa_dev_dev.sql` snapshots are updated as documentation, not as the deploy mechanism. Use `ALTER TABLE ... DROP COLUMN` guarded so a fresh re-run does not hard-fail.
- **Remove config constants and YAML section together.** Rationale: grep confirms no consumer, so deletion is safe; leaving either half would break config-shape tests or drip dead references.
- **Regenerate OpenAPI with `tools/generate-openapi.php`.** Rationale: the committed `openapi.json` files are generated artifacts; hand-editing drifts. The generator's `facebook_id` special cases (integer schema and example) are removed so regeneration stays clean.

## Risks / Trade-offs

- [Column drop is irreversible and loses any stored Facebook ids] → Back up `dev_users` before the migration; restore from backup is the rollback.
- [External consumers may still send or expect `facebook_id`] → Documented as **BREAKING**; unknown/extra payload fields are ignored by the mapper, and read responses simply omit the field.
- [Generator and committed docs drift if not regenerated in the same change] → Task includes regenerating and diffing both `openapi.json` copies.

## Migration Plan

1. Deploy code with the field mapping, rule, and config removals.
2. Apply `resources/migrations/drop_users_facebook_id.sql` to each environment.
3. Regenerate and commit OpenAPI.
4. Rollback: restore the pre-migration `dev_users` backup; the column reappears and the removed mappings can be restored from version control.

## Open Questions

- None.
