# Spec Delta

## Purpose

La API necesita una referencia pública, estable y versionada para que terceros puedan integrar sus clientes sin depender de autenticación ni de inspección del código fuente.

## ADDED Requirements

### Requirement: Documented fields match the module field map

The published OpenAPI document SHALL describe only the fields each module declares in its field map, so columns and keys removed from a module are no longer documented.

#### Scenario: Config module exposes the value field

- **WHEN** a reader inspects the documented `config` schema or its query parameters
- **THEN** the contract includes the `value` field
- **AND the contract does not mention a `data` or `public` field**

### Requirement: Operation inventory matches accepted module methods

The document SHALL include exactly the operations each routed module accepts and implements, and SHALL NOT document operations a module rejects.

#### Scenario: No collection listing for upload-only modules

- **WHEN** a reader inspects the contract for modules that only accept `store` (for example `attachments` and `frontend-logs`)
- **THEN** no collection `GET` operation is documented for those modules

#### Scenario: Bypass secret operation is documented

- **WHEN** a reader searches the contract for the hCaptcha bypass secret operation
- **THEN** `POST /hcaptcha` is documented with its request and response

### Requirement: Operation security matches module authentication declarations

Each documented operation SHALL carry the bearer security requirement if and only if the module declares that method as requiring authentication.

#### Scenario: Authenticated list operation

- **WHEN** a reader inspects an operation whose module method requires authentication (for example `GET /users`)
- **THEN** the operation declares the bearer security scheme

#### Scenario: Public write operation

- **WHEN** a reader inspects an operation whose module method allows anonymous access (for example `POST /users`)
- **THEN** the operation declares no security requirement

### Requirement: Error responses come from the response code catalog

Operations SHALL document error responses using codes defined in `app/core/config/api_codes.yml`, including codes added after the initial contract.

#### Scenario: Database query failure is documented

- **WHEN** a reader inspects the documented server-error responses of a data operation
- **THEN** a `500` response with code `APP_DATABASE_QUERY_FAILED` is documented

#### Scenario: Rate limit is documented

- **WHEN** a reader inspects the documented responses of an API operation
- **THEN** a `429` response with code `APP_RATE_LIMIT_EXCEEDED` is documented

### Requirement: Collection listings document the empty-list behavior

List operations SHALL document HTTP `200` with an empty data array for empty collections and SHALL NOT document `404` for an empty listing; item operations SHALL retain `404` for an unknown id.

#### Scenario: Empty collection responds with 200

- **WHEN** a reader inspects a collection `GET` operation
- **THEN** the operation documents a `200` response whose example data is an empty array

#### Scenario: Missing item responds with 404

- **WHEN** a reader inspects an item `GET` operation
- **THEN** the operation documents a `404` response for an unknown id

### Requirement: Success responses document the envelope schema

Every success response SHALL declare a JSON schema of the response envelope (`message`, `code`, `http_code`, `data`, `meta`) instead of a description alone.

#### Scenario: Reader inspects a list response

- **WHEN** a reader opens any documented `200` list response
- **THEN** the response declares a schema that describes the envelope fields

### Requirement: Operations carry stable unique operation ids

Every operation SHALL declare a unique `operationId`, and the value SHALL remain stable across regenerations as long as the module, path, and method do not change.

#### Scenario: Regeneration preserves identifiers

- **WHEN** the contract is regenerated from unchanged modules
- **THEN** every `operationId` in the new document equals the one in the previous document

### Requirement: Shared error responses are defined once

Error response definitions SHALL live in `components/responses` and be referenced by operations with `$ref`, so identical definitions are not repeated per operation.

#### Scenario: Reader follows an error reference

- **WHEN** a reader inspects any operation's documented error response
- **THEN** the response is a reference to a shared definition in `components/responses`

### Requirement: Contract stays synchronized with modules

The published document SHALL be regenerable from the current modules and codes, and an automated check SHALL fail when the published document does not match what the generator produces.

#### Scenario: Module edited without regenerating

- **WHEN** a module's field map or method declarations change and the published document is not regenerated
- **THEN** the automated synchronization check fails

#### Scenario: Document regenerated after a change

- **WHEN** the document is regenerated from unchanged modules and codes
- **THEN** the automated synchronization check passes
