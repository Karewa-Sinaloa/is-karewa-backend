# Proposal

## Why

The contracts API cannot currently store clarification ruling metadata or provide local backups of the four contract-related remote documents. Reusable, logged downloads with an authenticated individual endpoint and a cron-only bulk workflow will make these files available locally without putting bulk work on API request timeouts.

## What Changes

- Add nullable contract fields `proposal_backup`, `clarification_ruling_url`, and `clarification_ruling_backup`, and expose them through the contracts API and its OpenAPI contract.
- Define a reusable remote-document downloader that stores files under a configured `CONTRACTS_DIR`, records paths or the specified `error_404` / `error_400` markers in the `contratos` table, and logs each result to `logs/remote_download.log`.
- Use `tools/user-agents.json` to vary the cURL user agent, trying at most three distinct user agents per download.
- Add role-protected `POST /api/v5/contracts/{id}/download`, dispatched to a dedicated download action for roles 1, 2, and 3; keep ordinary contract creation on its existing store action.
- Add a CLI-only PHP entry point for bulk downloads. It will process contracts whose backup fields are null or empty while their corresponding source URLs are populated, and return a result for each attempted download.
- Update the published OpenAPI documents and their synchronization coverage for the new fields and individual operation.

## Capabilities

### New Capabilities
- `contract-document-downloads`: Contract document metadata, remote file retrieval and persistence, authenticated individual downloads, and cron-only bulk downloads.

### Modified Capabilities
- None. The existing OpenAPI synchronization capability already requires documented operations and fields to match their modules; this change adds the contract-specific API surface under the new capability.

## Impact

- Contracts module field map and validation, the `contratos` database table, shared download helper, configuration for `CONTRACTS_DIR`, runtime download logging, and a CLI cron entry point.
- API route dispatch for `POST /api/v5/contracts/{id}/download` using the existing `id`/`s` rewrite shape; no new rewrite rule is expected.
- OpenAPI generation and published documents at `api/docs/openapi.json` and `httpdocs/api/docs/openapi.json`.
- Existing user-agent input at `tools/user-agents.json` (100 entries). No dependency change is proposed.
