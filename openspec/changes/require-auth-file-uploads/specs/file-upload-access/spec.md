# Spec Delta

## Purpose

Describes who may upload attachment files through the API and under what per-client limits uploads are accepted. It covers access control, storage quota, and size limits for attachment uploads; the integrity of file content is covered by the `file-upload-integrity` capability.

## ADDED Requirements

### Requirement: Attachment upload requires authentication and a minimum role
The attachment storage method SHALL require authentication and SHALL be restricted to a minimum role set of administrative roles 1 to 3, matching the image-upload module. A request without a valid token SHALL be rejected with the standard authentication error before any file is processed, and a request whose role is outside the allowed set SHALL be rejected with the standard authorization error.

#### Scenario: Anonymous upload is rejected
- **WHEN** a request without an `Authorization` token sends a file to the attachment upload endpoint
- **THEN** the request is rejected with the standard authentication error (401)
- **AND** no file is processed or stored

#### Scenario: Authenticated user outside the role set
- **WHEN** a request carries a valid token whose role is outside the allowed set (roles 1 to 3) and sends a file
- **THEN** the request is rejected with the standard authorization error
- **AND** no file is processed or stored

#### Scenario: Authenticated user with an allowed role
- **WHEN** a request carries a valid token of role 1, 2, or 3 and sends a valid file
- **THEN** the upload is processed and the response uses the standard success envelope with the stored file data

### Requirement: Per-client storage quota
The system SHALL enforce a storage quota per authenticated client. When a new upload would exceed the client's quota, the request SHALL be rejected with a clear error code, and no file or directory SHALL be stored.

#### Scenario: Upload within quota
- **WHEN** an authenticated client whose stored volume is below the quota uploads a file that fits in the remaining quota
- **THEN** the upload succeeds and the client's stored volume grows by the size of the file

#### Scenario: Upload exceeding the quota
- **WHEN** an authenticated client whose remaining quota is smaller than the uploaded file sends the file
- **THEN** the request is rejected with the quota error code and HTTP 413
- **AND** no file is stored and the client's stored volume is unchanged

### Requirement: Upload size limit per file
The attachment upload SHALL enforce a maximum size per file. A file larger than the limit SHALL be rejected with the existing max-size error (906000, HTTP 413) before any file is stored.

#### Scenario: Oversized file is rejected
- **WHEN** a client uploads a file larger than the configured maximum size
- **THEN** the request is rejected with the max-size error (HTTP 413)
- **AND** no file is stored

#### Scenario: File at the limit is accepted
- **WHEN** a client uploads a file whose size is at or below the configured maximum and fits in the quota
- **THEN** the upload proceeds to content validation
