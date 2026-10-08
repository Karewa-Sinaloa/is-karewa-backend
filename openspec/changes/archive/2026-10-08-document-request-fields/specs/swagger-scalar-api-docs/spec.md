# Spec Delta

## ADDED Requirements

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
