# Design

## Context

See `proposal.md` for motivation and `specs/contract-document-downloads/spec.md` for the behavior contract. The contracts module currently queries `contratos`; existing `contract_backup` and `announcement_backup` fields are not writable in its normal API mapping. The existing rewrite maps module, ID, and subresource segments, while the method validator maps HTTP verbs to accepted module operations. `CONTRACTS_DIR` is not currently defined. The repository has SQL changes under `resources/migrations/`. The supplied user-agent list is `tools/user-agents.json` (100 entries).

## Goals / Non-Goals

**Goals:**
- Keep document retrieval reusable by both the API action and the cron process.
- Keep the individual operation distinct from normal contract creation and use the existing role-validation path.
- Persist only complete files and database outcomes, with bounded network work and auditable results.
- Preserve the documented four URL-to-backup mappings and the user's maximum of three distinct user agents per file.

**Non-Goals:**
- Expose the bulk operation over HTTP or add a public API for serving the local file bytes.
- Add third-party dependencies or change the existing REST framework.
- Refresh, discover, or maintain the user-agent list as part of this change.

## Decisions

1. **Use the contracts subresource and a dedicated action.** `POST /api/v5/contracts/{id}/download` maps through the existing `id`/`s` rewrite shape. Dispatch `s=download` from the module's accepted POST operation to a dedicated download action rather than putting download branching in the ordinary `store()` implementation. This preserves the existing create behavior and lets `ModuleHandler::Validate()` enforce authentication and roles 1, 2, and 3 before the method runs. A separate download module or new rewrite rule was considered but adds routing surface without benefit.

2. **Use one fixed mapping and validate input before I/O.** Accept only a selector from `proposal_url`, `clarification_ruling_url`, `contract_link`, and `call_link`; resolve the URL and backup columns server-side. Read the URL from the specified `contratos` row rather than accepting a client URL or arbitrary SQL column. This prevents arbitrary destination and column selection. Preserve the requested nullable string API fields; back-up paths are also written by the downloader.

3. **Share one download service between API and CLI.** Put the reusable behavior in the project's shared component/helper area. The service owns mapping, skip/error-marker checks, cURL retries, temporary-file handling, persistence, result construction, and logging. The API action is responsible for HTTP authorization and translating the service result into the standard API envelope; the CLI entry point invokes the same service without pretending to be a logged-in user.

4. **Bound and validate cURL attempts.** Parse `tools/user-agents.json`, choose user agents randomly, and use no more than three distinct entries per document. Treat connection failures and unsuccessful HTTP outcomes as retryable within that limit; classify a final HTTP 404 as `error_404` and all other exhausted failures as `error_400`. Verify TLS certificates, restrict protocols to HTTP(S), do not forward API credentials, and validate redirect destinations to prevent access to local/private services. Use timeouts and a temporary file; move it into the final location only after a successful response.

5. **Use configured storage and safe filenames.** Add `CONTRACTS_DIR` through the existing configuration mechanism and resolve it to a writable location suitable for contract files. Build filenames only from the database row ID, the fixed backup-column name, the contract ID, and a sanitized extension from the remote URL (fall back to a safe extension when absent). Store the resolved directory prefix plus filename in the database, not the literal text `CONTRACTS_DIR`. Do not derive a filesystem path from arbitrary client input.

6. **Make database changes additive.** Add the three nullable string columns to the `contratos` table using a SQL migration under `resources/migrations/`, consistent with the repository's existing migration location. Update test schema fixtures for the fields and exercise writes, nullability, and downloader persistence without depending on live network access.

7. **Keep bulk processing CLI-only.** Add a PHP command entry point that checks it is running under CLI, queries each mapped URL/backup pair for non-empty source URLs and null/empty backup values, and calls the shared service. Return/emit one result per attempted document. Do not route this operation through the module/API. Error markers are not considered empty for bulk selection; failed entries can be retried through the individual action, which explicitly permits error markers.

8. **Synchronize published API documentation.** Update the generator or its supported extension point to document the custom download operation, then regenerate and synchronize both published OpenAPI copies. Keep the existing rule that documented request bodies contain only fields writable by the module, and include the new nullable string fields and operation's authorization and outcomes.

## Risks / Trade-offs

- [Remote URL can target internal resources] → Restrict schemes and reject private/reserved destinations and unsafe redirects even when the URL came from a contract row.
- [Partial file or simultaneous API/cron download] → Download to a temporary file, atomically promote on success, and guard the database update so competing attempts do not replace an already-valid path.
- [Filesystem permissions or storage exhaustion] → Validate writable storage at startup/operation time, use cURL timeouts and a response-size bound, and report failures through the standard error marker and log.
- [Database schema/code deployment mismatch] → Apply the additive migration before deploying code that references the new fields; rollback code first and leave downloaded files intact.
- [Bulk query could create long-running cron work] → Keep it out of HTTP, process one document at a time, and ensure retries remain bounded per document.

## Migration Plan

1. Configure the deployment's `CONTRACTS_DIR` and ensure the PHP runtime can write to it and to `logs/remote_download.log`.
2. Apply the additive `contratos` migration before deploying module code and regenerated OpenAPI files.
3. Install the CLI entry point in the scheduled environment and configure its cron invocation only after migration and storage setup.
4. On rollback, disable the cron entry and roll back application code first. Retain downloaded files; drop the three new columns only if no deployed code or stored data depends on them.
