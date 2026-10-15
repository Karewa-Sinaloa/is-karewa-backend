# Spec Delta

## ADDED Requirements

### Requirement: Role assignment requires sufficient privilege
The `users` module SHALL NOT allow a caller to assign a `role_id` that is better (numerically lower) than the caller's own role, on `store` and on `update`. When a payload attempts it, the persisted value SHALL be corrected to a value permitted by the caller's role instead of being stored as requested. Anonymous or unauthenticated creates SHALL receive the default low-privilege role.

#### Scenario: Common user attempts self-promotion via update
- **WHEN** a user with role 5 sends `PUT /api/v5/users/{own-id}` with `{"role_id": 1}`
- **THEN** the stored `role_id` of that user remains 5
- **AND** the response uses the standard success envelope without elevating the caller

#### Scenario: Manager attempts to create an administrator
- **WHEN** a user with role 3 sends `POST /api/v5/users` with `{"role_id": 1}`
- **THEN** the stored `role_id` is corrected to 3 (the caller's own role)
- **AND** the user is not created with role 1

#### Scenario: Administrator assigns a permitted role
- **WHEN** a user with role 1 sends `PUT /api/v5/users/{id}` with `{"role_id": 2}` for a row the caller is allowed to edit
- **THEN** the stored `role_id` becomes 2

### Requirement: Privileged status field writes are restricted
The `users` module SHALL only allow `status_id` to be changed by administrative callers (roles 1 to 3). For any other caller, a `status_id` present in a `store` or `update` payload SHALL NOT alter the stored value.

#### Scenario: Non-administrative user tries to change a status
- **WHEN** a user with role 4 or 5 sends `PUT /api/v5/users/{own-id}` with `{"status_id": 2}`
- **THEN** the stored `status_id` remains unchanged
- **AND** no privilege or state change occurs for the caller

#### Scenario: Administrator changes a status
- **WHEN** a user with role 1 sends `PUT /api/v5/users/{id}` with `{"status_id": 2}`
- **THEN** the stored `status_id` becomes 2
