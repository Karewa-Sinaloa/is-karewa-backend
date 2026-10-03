# Design

## Context

See `proposal.md - Why`.

`ApiResponse::Set()` parses `api_codes.yml` with `Yaml::PARSE_OBJECT_FOR_MAP`, so `$codes` is an object whose properties are the codes. On failure it logs and sets `$codes = null`. The current flow assigns the fallback at lines 37-43 and then unconditionally reassigns `$response = $codes->$code;` at line 44, discarding the fallback. This helper is terminal: it calls `http_response_code()` and `die(json_encode(...))`, and it is the single output path for every endpoint.

## Goals / Non-Goals

**Goals:**
- Make the unknown-code and parse-failure paths return the internal-error envelope instead of fatalling.
- Keep the known-code path and metadata behavior unchanged.

**Non-Goals:**
- Changing any code in `api_codes.yml` or adding new codes.
- Restructuring the helper or its signature.
- Caching the parsed catalog (a separate concern).

## Decisions

- **Resolve the response object once, then use it.** Instead of assigning a fallback and then overwriting, compute `$response = ($codes && isset($codes->$code)) ? $codes->$code : (object) $fallback;` so the fallback survives. Alternative rejected: an early `if` that `die()`s a hardcoded error, which would duplicate the response rendering and skip metadata handling.
- **Keep the internal-error shape identical to the existing fallback.** Reuse the same `message`/`http_code`/`code` values already written, so behavior for existing clients is unchanged beyond not crashing.
- **Fix the log message concatenation** by separating the file path from the message with a delimiter, matching the style used elsewhere (for example `error_logs` entries joined with ` - `).

## Risks / Trade-offs

- Casting the fallback to an object changes how extra data is attached → Mitigation: use `(object)` for the fallback so `$response->$key` and `$response->meta` assignments behave the same as for catalog entries.
- A caller may now silently receive a 500 where it previously crashed → Accepted; a controlled envelope is strictly better and matches the catalog contract.

## Migration Plan

- No data or schema migration. Deploy the single helper change.
- Rollback: revert the file; no persisted state to undo.

## Open Questions

- None blocking.
