## Context

See `proposal.md` - the main constraint is to publish a public, versioned contract while keeping the canonical artifact separate from runtime code generation.

## Goals / Non-Goals

**Goals:**
- Keep the OpenAPI contract as a static, versioned JSON artifact.
- Make the docs UI publicly accessible without authentication.
- Preserve support for documenting both public and protected endpoints.
- Leave the published shape compatible with later static hosting such as GitHub Pages.

**Non-Goals:**
- Reworking API auth behavior.
- Changing endpoint semantics or response payloads.
- Designing a code-first spec generation pipeline.

## Decisions

- Use a committed JSON artifact at `api/docs/openapi.json` as the source of truth for documentation publishing.
- Keep Scalar public and read-only, consuming the JSON artifact rather than producing its own contract.
- Include security scheme definitions in the spec so protected routes are accurately described without hiding them from the public docs.
- Keep the artifact path under `api/docs/` rather than `httpdocs/` so the spec can later be copied or published to a static host without moving the canonical source.

Alternatives considered:
- Generating the JSON on demand: rejected because the requirement is a static, versioned artifact.
- Splitting public and protected docs now: rejected because the initial requirement is to show all endpoints and later narrow scope.

## Risks / Trade-offs

- Public docs can expose internal surface area -> Acceptable for now because the requirement explicitly asks to show public and protected endpoints.
- Static spec can drift from implementation -> Mitigation: keep the file versioned and verify it during release.
- Future hosting choices may need path tweaks -> Mitigation: keep the JSON artifact self-contained and avoid environment-specific assumptions.
