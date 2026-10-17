# Spec Delta

## Purpose

Provides contract document metadata and dependable remote-document downloads, with role-protected individual requests and a separate cron-only bulk workflow. Downloaded files are persisted locally and their outcomes remain observable through contract fields and structured results.

## ADDED Requirements

### Requirement: Contract document fields are available through the API
The contracts API SHALL expose `proposal_backup`, `clarification_ruling_url`, and `clarification_ruling_backup` as nullable string fields that can be read and written through the existing contracts operations.

#### Scenario: Read contract document metadata
- **WHEN** a client reads a contract through the public contracts API
- **THEN** the response includes the three fields when requested or included by the module's normal field selection
- **AND** each field may be null

#### Scenario: Write contract document metadata
- **WHEN** an authorized client creates or updates a contract with any of the three fields
- **THEN** the API persists the supplied values in the corresponding `contratos` columns

### Requirement: Remote documents use the supported URL-to-path mapping
A download request SHALL identify a contract and one supported source URL field; the system SHALL resolve the corresponding backup field from the fixed mapping and SHALL NOT accept an arbitrary URL or database column from the request.

#### Scenario: Resolve each supported document type
- **WHEN** a download is requested for `proposal_url`, `clarification_ruling_url`, `contract_link`, or `call_link`
- **THEN** the system uses respectively `proposal_backup`, `clarification_ruling_backup`, `contract_backup`, or `announcement_backup`

#### Scenario: Reject an unsupported source field
- **WHEN** a request names a source field outside the supported mapping
- **THEN** the request is rejected without making a network request or updating the database

### Requirement: Individual downloads require an authorized role
The API SHALL provide `POST /api/v5/contracts/{id}/download` for an individual document download and SHALL allow it only to authenticated users with roles 1, 2, or 3.

#### Scenario: Authorized individual download
- **WHEN** an authenticated user with role 1, 2, or 3 requests a supported document for the contract ID in the path
- **THEN** the API invokes the individual download action and returns its result

#### Scenario: Unauthenticated or disallowed role
- **WHEN** a request has no valid authentication or the user has a role outside 1, 2, and 3
- **THEN** the API rejects it before starting a remote download

#### Scenario: Ordinary contract creation remains unchanged
- **WHEN** a client sends a normal `POST /api/v5/contracts` request
- **THEN** the request continues through the existing contract creation operation and is not treated as a download

### Requirement: Individual downloads do not overwrite an existing backup
Before downloading an individual document, the system SHALL inspect the corresponding backup field; it SHALL download only when that value is null, empty, or an error marker, and otherwise report that the document is already downloaded.

#### Scenario: Backup is absent or records an earlier error
- **WHEN** the backup field is null, empty, `error_404`, or `error_400`
- **THEN** the system attempts a new download

#### Scenario: Backup already contains a path
- **WHEN** the backup field contains a non-empty path that is not an error marker
- **THEN** no remote request is made and the result indicates that the document was already downloaded

### Requirement: Successful downloads persist a local file and its path
For a successful remote response, the system SHALL save the file under `CONTRACTS_DIR` using the row ID, backup column name, and contract ID in the filename, then persist the relative path in the matching backup field of the `contratos` row.

#### Scenario: Save a successful download
- **WHEN** a supported remote file is downloaded successfully
- **THEN** the file is stored as `{row_id}_{backup_column}_{contract_id}.{extension}` under `CONTRACTS_DIR`
- **AND** the matching database field contains `CONTRACTS_DIR` plus the generated filename

### Requirement: Download failures are represented and logged
When a remote download fails, the system SHALL store `error_404` for an HTTP 404 and `error_400` for any other failure in the matching backup field, and SHALL record the attempt in `logs/remote_download.log` with its date, response, contract ID, and source URL.

#### Scenario: Remote file returns not found
- **WHEN** the remote server returns HTTP 404
- **THEN** the backup field is set to `error_404` and the failure is logged

#### Scenario: Any other download failure
- **WHEN** the request fails for a connection, permission, HTTP, or other reason other than 404
- **THEN** the backup field is set to `error_400` and the failure is logged

#### Scenario: Download result reports outcome
- **WHEN** an individual download completes or fails
- **THEN** its result reports success or failure and includes the date, response, contract ID, and source URL, with the applicable error code on failure

### Requirement: Remote requests use configured user agents with bounded retries
Remote file requests SHALL use cURL and a randomly selected user agent from `tools/user-agents.json`; each download SHALL try no more than three distinct user agents.

#### Scenario: Retry a failed remote request
- **WHEN** a request attempt fails because a user agent is rejected or the request otherwise fails
- **THEN** the downloader retries with a different configured user agent while respecting the three-item limit

#### Scenario: All configured attempts fail
- **WHEN** no attempt succeeds within the user-agent limit
- **THEN** the download fails with the `error_400` classification and reports a connection/download failure

### Requirement: Bulk downloads are available only to the cron process
The bulk operation SHALL be exposed through a PHP CLI entry point for cron use and SHALL NOT be callable through the public API.

#### Scenario: Bulk job selects pending files
- **WHEN** the cron process runs
- **THEN** it finds contracts whose mapped backup field is null or empty and whose mapped URL is not null or empty
- **AND** it attempts each eligible document using the shared download behavior

#### Scenario: Bulk job returns per-document results
- **WHEN** the cron process finishes processing its eligible documents
- **THEN** it returns an array containing the result of each attempted download, including success or failure, date, response, contract ID, source URL, and error code when applicable

#### Scenario: API cannot invoke bulk operation
- **WHEN** an HTTP request attempts to invoke the bulk operation
- **THEN** the request is not routed to or accepted by the CLI-only operation

### Requirement: OpenAPI describes the contract download API
The published OpenAPI documents SHALL describe the three contract fields and the individual download operation, including its role-protected authentication requirement, supported source-field input, and success and failure result.

#### Scenario: Inspect the contract schema
- **WHEN** a reader inspects the contracts OpenAPI schema and writable request body
- **THEN** the three nullable string fields are documented as readable and writable

#### Scenario: Inspect the individual download operation
- **WHEN** a reader inspects `POST /api/v5/contracts/{id}/download`
- **THEN** the path parameter, supported document selector, bearer authentication, role limitation, and result/error responses are documented
