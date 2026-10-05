# delete-association-guard Specification

## Purpose

Protects referential integrity at delete time by refusing to remove a record that is still referenced by an associated table, and reporting the relationship conflict to the client.

## Requirements

### Requirement: Block deletion of referenced records
The API SHALL reject a delete request when the target record is referenced by a row in any associated table declared for the module, and SHALL respond with the relationship error (`902002`, HTTP 409).

#### Scenario: Referenced record is blocked
- **WHEN** a client sends `DELETE /api/v5/{module}/{id}` and an associated table contains a row whose key equals `{id}`
- **THEN** the API responds with code `APP_ENTRY_RELATIONSHIP` (`902002`) and HTTP status 409
- **AND** no row is deleted

#### Scenario: Unreferenced record is deleted
- **WHEN** a client sends `DELETE /api/v5/{module}/{id}` and no associated table references `{id}`
- **THEN** the API deletes the record and responds with the normal delete success

### Requirement: Association lookup respects the configured table prefix
The association check SHALL query the associated table using the configured database prefix exactly once, so it succeeds whenever the prefixed table exists.

#### Scenario: Prefixed schema
- **WHEN** the configured prefix is non-empty (for example `dev_`) and the associated table exists as `{prefix}{table}`
- **THEN** the association check queries `{prefix}{table}` and does not fail with a database error

#### Scenario: Empty prefix
- **WHEN** the configured prefix is empty
- **THEN** the association check queries the bare table name

### Requirement: Association key derived from the request entry id
For modules whose association is keyed by the requested record, the guard SHALL compare the associated column against the resolved entry id of the request.

#### Scenario: User referenced by associated data
- **WHEN** a client requests deletion of a user that is referenced by an associated row (for example a customer whose `user_id` equals that user's id)
- **THEN** the deletion is blocked with `APP_ENTRY_RELATIONSHIP` (`902002`) and HTTP status 409

### Requirement: Modules without declared associations are unaffected
The guard SHALL be a no-op for modules that declare no associated tables.

#### Scenario: No associations declared
- **WHEN** a client deletes a record in a module that declares no associated tables
- **THEN** the delete proceeds without any association check query
