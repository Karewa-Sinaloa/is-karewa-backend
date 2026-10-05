# Authentication And Authorization

## Purpose

Describes how the Monitor Karewa backend authenticates requests and authorizes access to module methods. Agents MUST follow these rules when exposing or protecting endpoints.

## Requirements

### Requirement: JWT authentication
Authentication SHALL use JWT with the RS256 algorithm. Tokens SHALL be sent in the `Authorization` header.

#### Scenario: Authenticated request
- **WHEN** a module method requires authentication
- **THEN** the request carries a JWT in the `Authorization` header
- **AND** the token is validated with RS256

### Requirement: Key storage
JWT keys SHALL live in `app/.keys/`. Agents SHALL NOT commit keys or other secrets.

#### Scenario: Handling keys
- **WHEN** an agent needs JWT keys
- **THEN** it reads them from `app/.keys/` and never writes them into tracked files

### Requirement: Authorization during validation
`ModuleHandler::Validate()` SHALL check authentication and role access before the target method runs. `AUTHENTICATED` and `USER_ROLE` SHALL be defined after validation. Roles SHALL be integer IDs.

#### Scenario: Role-gated method
- **WHEN** a method declares allowed roles
- **THEN** `ModuleHandler::Validate()` grants access only when the authenticated user's role is in that list
- **AND** `AUTHENTICATED` and `USER_ROLE` are defined for the method

#### Scenario: Missing authentication
- **WHEN** an authenticated method is called without a token
- **THEN** the request is rejected before the method runs

### Requirement: Alternative hash authentication
An alternative link/webhook hash authentication path SHALL exist and SHALL be usable by modules that explicitly opt in. Its continued hardening is specified by the `hash-authentication` capability.

#### Scenario: Opting into hash authentication
- **WHEN** a module declares a hash slot for a method
- **THEN** the access check validates that hash before granting access

### Requirement: Local email and password authentication only
Authentication SHALL be performed only with a local email and password. The system SHALL NOT offer or reference social or third-party OAuth login (Facebook or similar), and the user identity SHALL NOT include a Facebook provider identifier.

#### Scenario: Login with email and password
- **WHEN** a user submits a valid email and password to the login endpoint
- **THEN** the request is authenticated without any social or OAuth provider

#### Scenario: No social login references
- **WHEN** the authentication surface and the user model are inspected
- **THEN** no Facebook provider identifier or social-login configuration is present
