# Spec Delta

## Purpose

Defines the base table names the role and user modules use for role and user-status data, so they resolve correctly regardless of the database prefix configured for the environment.

## ADDED Requirements

### Requirement: Role data uses the canonical base table name
The `roles` module SHALL read and write role records using the base table name `roles`, independent of the configured database prefix.

#### Scenario: Listing roles under a prefix
- **WHEN** a client requests `GET /api/v5/roles` and a non-empty prefix such as `dev_` is configured
- **THEN** the query targets `{prefix}roles` (for example `dev_roles`)
- **AND** the response contains role records with the standard success envelope

#### Scenario: Listing roles without a prefix
- **WHEN** a client requests `GET /api/v5/roles` and no prefix is configured
- **THEN** the query targets `roles`

### Requirement: User records join the canonical role and status tables
The `users` module SHALL join the `roles` and `users_status` base tables, independent of the configured database prefix.

#### Scenario: User listing resolves joined tables
- **WHEN** a client requests `GET /api/v5/users` with a configured prefix
- **THEN** the query joins `{prefix}roles` and `{prefix}users_status`
- **AND** each user includes its resolved role and status fields

### Requirement: User validation references the canonical tables
The `users` module SHALL validate the role and status foreign keys against the `roles` and `users_status` base tables.

#### Scenario: Valid role and status accepted
- **WHEN** a client submits a user whose `role_id` exists in `roles` and whose `status_id` exists in `users_status`
- **THEN** validation passes and the record is stored

#### Scenario: Unknown role rejected
- **WHEN** a client submits a user whose `role_id` does not exist in `roles`
- **THEN** the API rejects the payload with validation error `400000`
