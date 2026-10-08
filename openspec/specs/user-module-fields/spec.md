# user-module-fields Specification

## Purpose
Defines the field map, searchable columns, and validation rules the `users`
module exposes over its table, so every declared field resolves to a real column
under the configured database prefix.

## Requirements

### Requirement: User fields map to existing columns
Every field the `users` module declares SHALL map to a column that exists in the
users table, independent of the configured database prefix.

#### Scenario: Listing users resolves all fields
- **WHEN** a client requests `GET /api/v5/users` with a configured prefix
- **THEN** the query selects only columns that exist (for example
  `{prefix}users.recovery_date`, `middle_name`, `second_last_name`)
- **AND** the response contains user records with the standard success envelope
- **AND** no `Unknown column` database error is raised

#### Scenario: Unbacked fields are not exposed
- **WHEN** the module builds its field list
- **THEN** `phone_country_code`, `photo`, and `email_verified` are not present
- **AND** the field mapped to recovery date uses the `recovery_date` column

### Requirement: User validation references existing columns
The `users` module SHALL only validate fields that map to existing columns.

#### Scenario: Submitting a user without removed fields
- **WHEN** a client submits a user payload that omits `phone_country_code`,
  `photo`, and `email_verified`
- **THEN** validation does not reference the removed fields
- **AND** the record is stored using the mapped columns
