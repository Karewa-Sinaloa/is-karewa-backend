# Allowed Roles Disclosure

## Purpose

Discloses which roles may create, edit and delete a resource by including an `allowed_roles` field in authenticated API responses, while keeping anonymous responses free of authorization details.

## Requirements

### Requirement: Authenticated responses carry allowed_roles
Every response served for an authenticated request SHALL include a top-level `allowed_roles` field alongside `message`, `code`, `http_code` and `meta`.

#### Scenario: Success response on an authenticated method
- **WHEN** a client calls a method that requires authentication with a valid token and the method succeeds
- **THEN** the response envelope contains `allowed_roles` as a top-level field

#### Scenario: Error response on an authenticated method
- **WHEN** a method that requires authentication rejects the request for a business or validation reason (for example a failed validation) while the caller remains authenticated
- **THEN** the response envelope still contains `allowed_roles`

#### Scenario: Public method called with a valid token
- **WHEN** a method that allows anonymous access is called with a valid token
- **THEN** the response envelope contains `allowed_roles`

### Requirement: Anonymous responses omit allowed_roles
A response served without an established authenticated session SHALL NOT include the `allowed_roles` field.

#### Scenario: Public method called without a token
- **WHEN** a method that allows anonymous access is called without a token
- **THEN** the response envelope has no `allowed_roles` key

#### Scenario: Rejected authentication
- **WHEN** a request without a valid token is rejected before the method runs (for example a missing or expired token)
- **THEN** the response envelope has no `allowed_roles` key

### Requirement: allowed_roles shape and derivation
The `allowed_roles` value SHALL be an object keyed by the actions `create`, `edit` and `delete`, mapped from the module's declared create, update and delete methods; each value SHALL be the array of allowed role IDs as integers, where an empty array means any authenticated role, and keys for actions the module does not declare SHALL be omitted. The field SHALL report the module's declarations for the current request, not only the action being invoked.

#### Scenario: Module declares all three actions
- **WHEN** a module declares create with roles 1, 2 and 3, update with role 1, and delete with roles 1 and 2, and an authenticated client reads the collection
- **THEN** the response's `allowed_roles` is `{ "create": [1, 2, 3], "edit": [1], "delete": [1, 2] }`

#### Scenario: Authenticated method without a role restriction
- **WHEN** a module declares an authenticated method with no role list
- **THEN** that action's value in `allowed_roles` is an empty array

#### Scenario: Module declares only some actions
- **WHEN** a module accepts only a create method among the three standard actions
- **THEN** `allowed_roles` contains only the `create` key

#### Scenario: Module declares no standard actions
- **WHEN** an authenticated response comes from a module that declares none of the create, update or delete methods
- **THEN** `allowed_roles` is present as an empty object
