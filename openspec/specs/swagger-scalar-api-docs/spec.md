# Swagger / API Docs

## Purpose

La API necesita una referencia pública, estable y versionada para que terceros puedan integrar sus clientes sin depender de autenticación ni de inspección del código fuente.

## Requirements

### Requirement: Documented fields match the module field map

The published OpenAPI document SHALL describe only the fields each module declares in its field map, so columns and keys removed from a module are no longer documented.

#### Scenario: Config module exposes the value field

- **WHEN** a reader inspects the documented `config` schema or its query parameters
- **THEN** the contract includes the `value` field
- **AND the contract does not mention a `data` or `public` field**

#### Scenario: Config module exposes the is_private field

- **WHEN** a reader inspects the documented `config` schema or its query parameters
- **THEN** the contract includes `is_private` as a boolean field with default `true`
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

#### Scenario: Config item operation is public

- **WHEN** a reader inspects `GET /config/{id}`, whose module method allows anonymous access
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

### Requirement: Row visibility rules are described in the contract
The list and item operations of the config module SHALL carry a description of the row visibility rule, so a reader knows that callers without roles 1, 2 or 3 only see entries with `is_private = 0` and receive `404000` for a private entry.

#### Scenario: Reader inspects the config list operation

- **WHEN** a reader opens the documented `GET /config` operation
- **THEN** the operation declares a description stating that private entries are excluded for callers without roles 1, 2 and 3

#### Scenario: Reader inspects the config item operation

- **WHEN** a reader opens the documented `GET /config/{id}` operation
- **THEN** the operation declares a description stating that a private entry answers `404000` for callers without roles 1, 2 and 3

### Requirement: Config permission fields are documented as readable only
The published document SHALL list `edit_roles` among the readable `config` fields and query parameters, and SHALL NOT include it in the request bodies of `POST /config` or `PUT /config/{id}`, mirroring that the field is written only from the database.

#### Scenario: Reader inspects the config schema

- **WHEN** a reader inspects the documented `config` query parameters or response examples
- **THEN** `edit_roles` is documented as a readable field

#### Scenario: Reader inspects a config write body

- **WHEN** a reader inspects the documented request body of `POST /config` or `PUT /config/{id}`
- **THEN** `edit_roles` is not documented as a writable property

### Requirement: Row permission denials are documented
The write operations of the `config` module SHALL document the `403` response defined by the response code catalog for a row the caller is not allowed to modify.

#### Scenario: Reader inspects the config update operation

- **WHEN** a reader opens the documented `PUT /config/{id}` operation
- **THEN** a `403` response carrying `APP_AUTH_ROW_FORBIDDEN` is documented

#### Scenario: Reader inspects the config delete operation

- **WHEN** a reader opens the documented `DELETE /config/{id}` operation
- **THEN** a `403` response carrying `APP_AUTH_ROW_FORBIDDEN` is documented

### Requirement: Request bodies document only writable fields
Request body schemas for create and update operations SHALL list only the fields the module field map declares as writable (saved and not read-only), so read-only columns, joined values and server-managed timestamps are never presented as sendable.

#### Scenario: Create payload omits read-only fields
- **WHEN** a reader inspects the request body of `POST /contracts`
- **THEN** the schema properties exclude `id`, `created_at`, `updated_at`, the `*_backup` columns and the joined `*_name` fields
- **AND** every property that remains is a field the API writes on create

#### Scenario: Create payload keeps writable fields with defaults
- **WHEN** a reader inspects a writable field that declares a default in the module field map
- **THEN** the schema property documents that default

### Requirement: Create operations document required fields
Create operations SHALL declare a `required` list built from the module validation rules whose rule string contains `required`, restricted to fields the request body can carry.

#### Scenario: Required fields are listed
- **WHEN** a reader inspects the request body of `POST /users`
- **THEN** `required` contains `email`, `first_name` and `last_name`
- **AND** fields the module cannot write, such as `id`, never appear in `required`

#### Scenario: Rules without required leave the list empty
- **WHEN** a module declares validation rules but none of them require a writable field
- **THEN** the request body schema declares no `required` list

### Requirement: Request field schemas carry validation constraints
Each documented request property SHALL state its type and format, the constraints implied by the module validation rules, and whether the field is required or optional, so a reader can build a valid payload without reading the module source.

#### Scenario: Field with length and existence rules
- **WHEN** a reader inspects a field whose rules declare a maximum length and an `exist` reference
- **THEN** the property documents the corresponding `maxLength` and describes the referenced table and column

#### Scenario: Field with format rules
- **WHEN** a reader inspects fields whose rules declare `email`, `url` or `date_format`
- **THEN** the properties declare the matching `email`, `uri` or `date` format

### Requirement: Update operations document partial update semantics
Update operations SHALL not declare a required list, and SHALL state that only fields sent with a non-empty value are updated while omitted or empty fields keep their current value.

#### Scenario: Reader inspects an update payload
- **WHEN** a reader opens the request body of `PUT /contracts/{id}`
- **THEN** the schema documents the partial update behavior
- **AND** the schema declares no `required` list

### Requirement: Collection query parameters enumerate allowed values
Collection list operations SHALL document, for `fields`, `sort` and `groupby`, the module field names the API accepts, SHALL restrict `embed` to the supported value `pagination`, and SHALL describe each filterable field with its type and validation constraints.

#### Scenario: Reader selects output fields
- **WHEN** a reader inspects the `fields` parameter of `GET /roles`
- **THEN** the description lists the selectable fields of the module

#### Scenario: Reader sorts the collection
- **WHEN** a reader inspects the `sort` parameter of a collection operation
- **THEN** the description lists the sortable fields and the example uses fields that exist in that module

#### Scenario: Reader embeds pagination
- **WHEN** a reader inspects the `embed` parameter of a collection operation
- **THEN** the parameter declares `pagination` as the supported value

### Requirement: Code samples are human readable
Every operation SHALL publish its own cURL and axios samples, written against the public API origin, whose query strings and bodies carry the documented example values verbatim so a reader never sees percent-encoded examples such as `id%2Cname`, `eq%3A1` or `%2Bid`.

#### Scenario: Reader copies a list request
- **WHEN** a reader opens the code samples of `GET /roles`
- **THEN** the samples show `sort=+id,-name`, `fields=id,name` and `name=lk:texto` without percent-encoding
- **AND** the samples address `https://kapi.chavodigital.com`

#### Scenario: Reader copies a body request
- **WHEN** a reader opens the code samples of `POST /users`
- **THEN** the cURL and axios samples include the documented request body example

#### Scenario: Generated clients stay hidden
- **WHEN** a reader opens the documentation reference
- **THEN** the reference does not render the HTTP clients it derives from the document, since those percent-encode the parameter examples

### Requirement: Public OpenAPI document

The system MUST provide a public OpenAPI JSON document at `api/docs/openapi.json`.

#### Scenario: Document is reachable without authentication

- **WHEN** a visitor requests `api/docs/openapi.json`
- **THEN** the system returns the OpenAPI document without requiring credentials

#### Scenario: Document is versioned

- **WHEN** the document is published
- **THEN** it includes explicit API version metadata in the OpenAPI `info` block

### Requirement: Public Scalar documentation UI

The system MUST expose a public Scalar-based documentation UI without authentication.

#### Scenario: Anonymous visitor opens docs UI

- **WHEN** an anonymous visitor accesses the documentation interface
- **THEN** they can browse the API documentation without signing in

#### Scenario: UI consumes the public JSON spec

- **WHEN** the documentation UI loads
- **THEN** it reads the OpenAPI definition from the public JSON document

### Requirement: Document protected and public endpoints

The system MUST include both public and protected endpoints in the OpenAPI document until a later scope change removes protected coverage.

#### Scenario: Protected endpoint is listed

- **WHEN** the OpenAPI document is generated or maintained
- **THEN** protected endpoints are present in the published contract

#### Scenario: Security scheme is described

- **WHEN** a protected endpoint is documented
- **THEN** the spec includes the required security scheme and usage expectations

### Requirement: Future GitHub Pages compatibility

The documentation structure MUST remain compatible with future hosting from GitHub Pages as a static site.

#### Scenario: Static hosting can reuse the spec artifact

- **WHEN** the docs are later published from a static host
- **THEN** the existing JSON artifact can be served without redesigning the contract

#### Scenario: No server-only coupling is introduced

- **WHEN** the documentation is reviewed for portability
- **THEN** it does not depend on a runtime-only generation flow for its published contract
