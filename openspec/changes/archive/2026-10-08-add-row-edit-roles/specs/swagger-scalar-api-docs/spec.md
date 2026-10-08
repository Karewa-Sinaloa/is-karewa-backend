# Spec Delta

## ADDED Requirements

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
