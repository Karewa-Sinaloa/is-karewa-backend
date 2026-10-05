# Spec Delta

## Purpose

Defines the administrator-only hCaptcha bypass secret used to log in from API clients such as Postman or automated tests, including how the secret is generated, stored, presented, and revoked.

## ADDED Requirements

### Requirement: Bypass applies only to administrators
The hCaptcha bypass SHALL be honored only for users whose role is the administrator role (`role_id = 1`). Requests from every other role SHALL be subject to normal captcha verification.

#### Scenario: Administrator with matching secret
- **WHEN** login presents a valid bypass secret for an enabled administrator account
- **THEN** the captcha verification is skipped for that login
- **AND** credential verification still runs

#### Scenario: Non-administrator presents a secret
- **WHEN** login presents a bypass secret for a user whose role is not the administrator role
- **THEN** the secret is not honored
- **AND** normal captcha verification is still required

#### Scenario: Wrong or unknown secret
- **WHEN** login presents a bypass value that does not match the account's stored secret
- **THEN** the secret is not honored
- **AND** normal captcha verification is still required

### Requirement: Bypass secret is stored hashed and never exposed
The bypass secret SHALL be stored in the users table as an irreversible hash and SHALL NOT be returned by any read endpoint. The plaintext SHALL be shown only once, at the moment of generation or rotation.

#### Scenario: Stored form
- **WHEN** a bypass secret is persisted
- **THEN** only a hash of the secret is written to the users table
- **AND** the plaintext is not stored

#### Scenario: Reading a user
- **WHEN** any user read endpoint returns user data
- **THEN** the bypass secret hash is not included in the response

### Requirement: Secret generation and rotation is administrator-only
An authenticated administrator SHALL be able to generate or rotate their own bypass secret. The operation SHALL return the plaintext once and replace any previous secret. Non-administrators SHALL NOT be able to generate a bypass secret for any account.

#### Scenario: Administrator generates a secret
- **WHEN** an authenticated administrator requests a new bypass secret
- **THEN** a new random secret is generated, its hash is stored, and the plaintext is returned once

#### Scenario: Rotation invalidates the previous secret
- **WHEN** an administrator generates a new secret while an old one exists
- **THEN** the previous secret no longer authenticates
- **AND** only the most recently issued secret is honored

#### Scenario: Non-administrator requests a secret
- **WHEN** a non-administrator attempts to generate a bypass secret
- **THEN** the request is denied

### Requirement: Bypass is opt-in per request
The bypass SHALL be activated only when the login request explicitly presents the secret in the designated request header. Absence of the header SHALL leave captcha verification unchanged for all logins.

#### Scenario: Header absent
- **WHEN** login does not include the bypass header
- **THEN** normal captcha verification applies

#### Scenario: Header present and valid for administrator
- **WHEN** login includes the bypass header with a secret matching an administrator's stored hash
- **THEN** captcha verification is skipped for that request only

### Requirement: Bypass mechanism is documented
`README.md` SHALL document the bypass mechanism: the header name, its administrator-only scope, how to obtain and rotate the secret, and a warning that it is intended for testing and trusted API clients.

#### Scenario: Reader looks up the bypass
- **WHEN** a reader consults `README.md` for how to log in from Postman or tests
- **THEN** the header name, the administrator-only restriction, and the generation/rotation steps are documented
