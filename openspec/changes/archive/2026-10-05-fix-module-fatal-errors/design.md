# Design

## Context

See `proposal.md - Why`.

`BaseModel::__construct(array $additionalData = [])` already loads the decoded request payload into `$this->payload`; the `$additionalData` argument exists only to merge derived fields (such as a generated slug). The `Crud` trait supplies `show`/`index`/`store`/`update`/`destroy`, each delegating to the corresponding model method (`get`/`post`/`put`/`delete`). The `AppConfig` controller currently declares all five methods itself, four of which delegate directly to the model while `destroy()` is bespoke and references undefined symbols.

## Goals / Non-Goals

**Goals:**
- Remove the two PHP fatals so `/api/v5/roles` and `/api/v5/config` respond through the standard envelope.
- Keep the declared methods, routes, and response codes unchanged.

**Non-Goals:**
- Correcting the schema drift (`user_roles`/`user_status`, `config` `data`/`public` vs `value`); those keep producing standard `902000` database errors.
- Reworking `config.moduleFields` or the ORM's error mapping.

## Decisions

- **`roles`: construct with no additional data.** Call `parent::__construct()` instead of passing `$_payload`. `BaseModel` already exposes the payload, and `Roles` derives no fields, so merging the payload a second time adds nothing. Alternative rejected: casting `$_payload` to array, which duplicates the payload and could inject unexpected keys.
- **`config`: adopt the `Crud` trait and drop the redundant overrides.** Four of the five overridden methods already call the model directly; the trait expresses the same behavior and provides a correct delete. This removes the broken `destroy()` and the duplicated boilerplate in one move. Alternative rejected: minimally rewrite `destroy()` to delegate, which leaves five hand-written passthroughs likely to drift again.
- **`config` has no association guard.** Configuration rows are not referenced by other tables, and the existing entry checked `users.role_id`, which was copied from `roles`. Alternative rejected: correcting the association entry, since no real relationship exists to encode.

## Risks / Trade-offs

- Replacing the overrides could change behavior if any of them differed from the trait → Mitigation: they delegate directly to the same model methods the trait calls; only `destroy()` differed and was non-functional.
- Configuration deletion becomes permanent (previously it fataled, so it never deleted) → Accepted; this matches other CRUD modules.
- Remaining schema drift still yields `902000` for some `roles`/`config` requests → Accepted and documented as a non-goal; those failures are standard envelopes, not fatals.

## Migration Plan

- No data or schema migration. Deploy the two controller changes together.
- Rollback: revert both files; no persisted state to undo.

## Open Questions

- None blocking. The schema-drift and `moduleFields` corrections are deferred to a separate change.
