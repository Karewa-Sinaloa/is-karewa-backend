# Spec Delta

## ADDED Requirements

### Requirement: Login access-token lifetime honors configured session time
A successful login SHALL issue an access token that expires after the configured session time (`SESSION_TIME`, 86400 s) rather than a fixed one-year lifetime. A longer-lived token SHALL only be issued when the user explicitly opts in to a persistent session at login (keep_session).

#### Scenario: Default login uses the configured session time
- **WHEN** a user logs in with valid credentials without requesting a persistent session
- **THEN** the issued access token expires after the configured session time (86400 s)
- **AND** the login response reports that expiration to the client

#### Scenario: Explicit persistent-session opt-in
- **WHEN** a user logs in with valid credentials and explicitly requests a persistent session (keep_session)
- **THEN** the issued access token expires after the extended one-year lifetime
- **AND** omitting the opt-in still yields the configured session time

#### Scenario: No fixed one-year lifetime by default
- **WHEN** any login is performed without the persistent-session opt-in
- **THEN** the token expiration is not the fixed one-year value

### Requirement: Session lifetime configuration is the single source for expiry
Token expiry SHALL be derived from the configured session-time setting (`SESSION_TIME`) instead of a hard-coded one-year constant, so changing the configuration changes newly issued token lifetimes.

#### Scenario: Configuration change affects new logins
- **WHEN** the configured session time is changed and a user logs in afterwards
- **THEN** the newly issued token expires after the newly configured duration

#### Scenario: Existing sessions keep their issued expiry
- **WHEN** a token was already issued before the configuration change
- **THEN** its expiry remains the one recorded at issuance until it expires or is revoked
