# Design

## Context

See `proposal.md - Why`.

`BaseModel::getParamsFilters()` reads each filterable query param, applies `explode(':', $v)` without a limit (line 243), then treats `$v[0]` as an operator when it matches the operator map and `$v[1]` as the value. `DBGet::get_filters()` (get.php) contains two identical `elseif ($value[2] == 'IN')` branches; the second is unreachable. `DB::connection()` constructs a `new PDO` on every call and four helpers call it. The `users` module builds `$this->table_assoc` in its constructor with `'value' => $id`, where `$id` is undefined; the private field `$id` exists but is not used there, and `Crud::destroy()` calls `BaseModel::delete()`, which passes `$this->table_assoc`. The connection seam and prefix override are introduced by `harden-orm-layer` and `add-orm-tests`.

## Goals / Non-Goals

**Goals:**
- Correct colon parsing and the dead association guard with minimal, behavior-focused edits.
- Make connection reuse compatible with the injection seam.
- Remove the unreachable branch without altering SQL.

**Non-Goals:**
- Reworking the operator map or adding new operators.
- Changing the module-level `queryFields` merge semantics.
- Changing `users` beyond the association guard value.
- Connection pooling across requests.

## Decisions

- **Split on the first colon only.** Use `explode(':', $v, 2)`; when the prefix is a known operator, the remainder (colon included) is the value, and otherwise the whole string is an equality value. Alternative rejected: splitting on every colon and re-joining, which is more code for the same result.
- **Guard on the current identifier.** Use the record id already resolved by the request (`$this->entryId`, or the module's stored id) rather than the undefined local, so the guard compares to the current record. Alternative rejected: reading `$_GET['id']` in the constructor, which bypasses the module's id handling.
- **Cache the connection statically, respecting the override.** Keep an instance in `DB` and return it while set; the injected connection takes precedence. Alternative rejected: a local static inside `connection()`, which cannot be reset between tests.
- **Delete the second `IN` branch.** The first branch is the effective one; removing the duplicate is a pure dead-code removal, verified by comparing the generated SQL before and after.

## Risks / Trade-offs

- Changing colon parsing alters filters that previously "worked" by accident on multi-colon values → Mitigation: the new behavior is the documented one, and a test pins plain, `eq:`, and colon-containing values.
- The association guard now rejects deletes that previously passed through the undefined-value path → Mitigation: this is the intended enforcement; a test covers both associated and free records.
- Connection caching can serve a stale connection after a config change within a request → Accepted; config does not change mid-request, and tests reset the override.
- The duplicate `IN` removal could hide a subtle dependence on the second branch → Mitigation: the branches are byte-identical; assert the SQL string is unchanged.

## Migration Plan

- Land after `harden-orm-layer`; if that change adds the connection seam, express caching on top of it.
- Deploy the four edits together; run the ORM tests.
- Rollback: revert the files; no persisted state changes.

## Open Questions

- None blocking.
