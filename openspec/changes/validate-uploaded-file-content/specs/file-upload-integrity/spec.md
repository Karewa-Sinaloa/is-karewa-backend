# Spec Delta

## Purpose

Describes how uploaded files are validated and stored so that uploads cannot smuggle active content into the API's origin: allowed type is decided by extension whitelist plus real content inspection, files are stored under non-guessable random names, rejected uploads leave nothing behind, and stored files are served as non-executable static content. Access control and per-client limits are covered by the `file-upload-access` capability.

## ADDED Requirements

### Requirement: Allowed type is decided by extension and real content
The allowed type of an uploaded file SHALL be determined by an extension whitelist combined with inspection of the file's real content (signature/MIME sniffing). The client-supplied `Content-Type` header SHALL NOT be used to decide whether a file is allowed.

#### Scenario: File with a forged Content-Type header
- **WHEN** a client uploads a file whose `Content-Type` header claims an allowed type but whose real content matches a disallowed type
- **THEN** the upload is rejected with the invalid-format error (HTTP 415)
- **AND** no file is persisted

#### Scenario: Extension outside the whitelist
- **WHEN** a client uploads a file whose extension is not in the whitelist for the endpoint
- **THEN** the upload is rejected with the invalid-format error (HTTP 415)

#### Scenario: Legitimate file whose content matches its extension
- **WHEN** a client uploads a file whose extension is in the whitelist and whose real content matches that type
- **THEN** the upload is accepted regardless of the exact `Content-Type` header value sent

### Requirement: Files are stored under non-guessable names
Uploaded files SHALL be stored under a random, non-guessable name (such as a UUID) that preserves only the validated extension; the original client filename SHALL be kept only as metadata and SHALL NOT determine any part of the stored path.

#### Scenario: Stored name does not reveal the original
- **WHEN** a client uploads a file originally named `shell.php.jpg` or `nota.pdf.html`
- **THEN** the stored file name is random and contains no part of the original name
- **AND** the original name is returned only as metadata in the response

### Requirement: Rejected uploads are not persisted
When any upload validation fails, the request SHALL return a validation error and SHALL NOT persist any file or directory on the server, leaving the stored volume unchanged.

#### Scenario: Failed validation leaves no trace
- **WHEN** an upload fails content or extension validation
- **THEN** the response carries the validation error code
- **AND** no file or directory for that upload exists on the server

### Requirement: Stored uploads are served as non-executable static content
Stored uploads SHALL be served as static content whose type is fixed by the server, with content sniffing disabled, and SHALL NOT be interpretable as executable code or active markup by the client.

#### Scenario: Serving a stored upload
- **WHEN** a stored upload is requested by its URL
- **THEN** it is delivered as static content with a server-fixed content type and sniffing disabled
- **AND** the response cannot trigger script execution in the API origin
