# Tasks

## 1. Contract fields and schema

- [ ] 1.1 Add a nullable-column SQL migration for `proposal_backup`, `clarification_ruling_url`, and `clarification_ruling_backup` on `contratos`, and update contract test fixtures; verify the migration applies and the fixture contains the fields.
- [ ] 1.2 Expose the three fields as nullable, readable, and writable contract fields with matching validation; add CRUD tests for null and string values and verify unauthorized writes remain rejected.
- [ ] 1.3 Regenerate both published OpenAPI documents for the new fields and add/adjust schema assertions; verify `OpenApiSyncTest` passes and the request schemas list the fields as writable.

## 2. Shared remote downloader

- [ ] 2.1 Configure `CONTRACTS_DIR` and implement the fixed four-pair URL/backup mapping, safe filename construction, existing-path skip behavior, and persistence to `contratos`; verify unit tests cover every mapping, null/empty/error-marker retry, and already-downloaded behavior.
- [ ] 2.2 Implement cURL retrieval using `tools/user-agents.json`, with at most three distinct user agents, bounded time/response size, TLS verification, HTTP(S)-only requests, and safe destination/redirect validation; verify mocked transport tests cover retries, success, and exhausted attempts without live network access.
- [ ] 2.3 Implement atomic temporary-file promotion, `error_404`/`error_400` persistence, structured return values, and `logs/remote_download.log` entries; verify tests assert stored paths, failure classifications, result data, logging, and cleanup of partial files.

## 3. Individual download API

- [ ] 3.1 Add the dedicated `POST /api/v5/contracts/{id}/download` action using the existing route shape and accepted-method validation for roles 1, 2, and 3; verify integration tests cover authorized access, unauthenticated and disallowed-role rejection before network I/O, invalid selectors, and unchanged normal contract creation.
- [ ] 3.2 Document the custom operation in the OpenAPI generator or its supported extension point, regenerate both published documents, and add operation assertions; verify the operation documents its path ID, supported selector, bearer security, roles, and outcomes and `OpenApiSyncTest` passes.

## 4. Cron-only bulk downloads

- [ ] 4.1 Add a PHP CLI-only entry point that processes all eligible URL/backup pairs in `contratos` through the shared downloader and returns a per-document result array; verify tests cover all four mappings, null/empty backup selection, non-empty URLs, excluded error markers, and that HTTP execution is refused.
- [ ] 4.2 Document the deployment prerequisites and cron invocation, including storage/log permissions and migration order; verify the documented command runs in CLI mode and fails safely when configuration or required paths are unavailable.

## 5. Integration verification

- [ ] 5.1 Run the focused downloader, contracts, authorization, and OpenAPI tests plus the full PHPUnit suite; verify all pass without live network dependencies.
- [ ] 5.2 Validate the change artifacts and confirm the migration, API docs, CLI entry point, and tests agree with `specs/contract-document-downloads/spec.md`; verify `openspec validate --change add-contract-document-downloads` succeeds.
