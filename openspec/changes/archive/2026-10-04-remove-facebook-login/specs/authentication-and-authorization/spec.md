# Spec Delta

## ADDED Requirements

### Requirement: Local email and password authentication only
Authentication SHALL be performed only with a local email and password. The system SHALL NOT offer or reference social or third-party OAuth login (Facebook or similar), and the user identity SHALL NOT include a Facebook provider identifier.

#### Scenario: Login with email and password
- **WHEN** a user submits a valid email and password to the login endpoint
- **THEN** the request is authenticated without any social or OAuth provider

#### Scenario: No social login references
- **WHEN** the authentication surface and the user model are inspected
- **THEN** no Facebook provider identifier or social-login configuration is present
