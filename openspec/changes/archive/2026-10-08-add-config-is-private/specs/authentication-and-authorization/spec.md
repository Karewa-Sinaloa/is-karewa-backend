# Spec Delta

## ADDED Requirements

### Requirement: Optional authentication honors a presented token
When a method does not require authentication, the validation step SHALL still validate a token that the request presents and SHALL expose the caller's role when that token is valid, and SHALL NOT reject the request when the token is absent or invalid.

#### Scenario: Valid token on a public method
- **WHEN** a method allows anonymous access and the request carries a valid token
- **THEN** `AUTHENTICATED` and `USER_ROLE` are defined from that token
- **AND** the method runs with the caller's role available to the module

#### Scenario: No token on a public method
- **WHEN** a method allows anonymous access and the request carries no token
- **THEN** the method runs without a role

#### Scenario: Invalid token on a public method
- **WHEN** a method allows anonymous access and the request carries an absent, expired or malformed token
- **THEN** the method runs as anonymous
- **AND** no authentication error is returned for the rejected token
