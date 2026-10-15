# Spec Delta

## ADDED Requirements

### Requirement: Caller role and status are revalidated on each authenticated request
On every authenticated request the system SHALL revalidate the caller's current role and account status against the database instead of trusting only the role stored in the token. If the caller's `role_id` or `status_id` differs from the values bound to the token, the active session SHALL be revoked and the request SHALL be rejected as a revoked/superseded session.

#### Scenario: Demoted administrator presents a still-valid token
- **WHEN** a user whose role was lowered after the token was issued sends an authenticated request with that token
- **THEN** the request is rejected with the session-revoked error
- **AND** the user must log in again to obtain a token reflecting the new role

#### Scenario: Deactivated user presents a still-valid token
- **WHEN** a user whose account status was changed to inactive after the token was issued sends an authenticated request with that token
- **THEN** the request is rejected
- **AND** the active session for that user is revoked

#### Scenario: Unchanged role and status
- **WHEN** an authenticated request presents an active token whose role and status match the database values
- **THEN** access is granted
- **AND** the caller's role used for authorization is the current database role

#### Scenario: Demotion takes effect before token expiry
- **WHEN** a user's role is lowered while their token has not yet expired
- **THEN** the next authenticated request with that token no longer grants the previous role's access, without waiting for token expiry
