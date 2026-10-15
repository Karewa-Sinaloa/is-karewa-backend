# Spec Delta

## Purpose

Define una única política de contraseñas aplicada en todos los puntos donde una contraseña se acepta para fijarla (alta, actualización y restablecimiento), con el mismo criterio para cualquier rol del caller y sin permitir que una contraseña quede por debajo de la política.

## ADDED Requirements

### Requirement: One policy applied wherever a password is set
The system SHALL apply a single password policy at every point where a new password is accepted: user creation, user update, and password reset. The policy SHALL require at least 10 characters and SHALL reject passwords on the common-password list. The same policy SHALL apply regardless of the caller's role.

#### Scenario: Creation with a password below the minimum
- **WHEN** a user is created with an 8-character password
- **THEN** the request is rejected with a validation error
- **AND** no user is created

#### Scenario: Reset with a password below the minimum
- **WHEN** a password reset submits a 9-character password
- **THEN** the request is rejected with a validation error
- **AND** the stored password remains unchanged

#### Scenario: Same policy for every caller role
- **WHEN** an administrator sets a password for another user while creating or resetting it
- **THEN** the password is validated against the same policy applied to anonymous or self-service callers

### Requirement: An existing password cannot be weakened below the policy
A user update that carries a new password SHALL be rejected when the new password does not satisfy the policy, and the stored password SHALL remain unchanged. An update that does not carry a password SHALL succeed without requiring one.

#### Scenario: Update tries to shorten the password below the policy
- **WHEN** an update payload carries a 6-character password
- **THEN** the request is rejected with a validation error
- **AND** the stored password is still verifiable against the previously stored value

#### Scenario: Update without a password
- **WHEN** an update payload carries no password
- **THEN** the update proceeds and the stored password is unchanged

### Requirement: Common passwords are rejected
A new password that appears on the application's common-password list SHALL be rejected even when it satisfies the length requirement.

#### Scenario: Password from the common list
- **WHEN** a new password of at least 10 characters is on the common-password list
- **THEN** the request is rejected with a validation error

### Requirement: Setting policy does not apply to credential verification
Presenting an existing password for verification (for example, signing in) SHALL NOT apply the password-set policy, so accounts whose stored password predates the policy remain usable.

#### Scenario: Existing shorter password still signs in
- **WHEN** a user whose stored password has fewer than 10 characters signs in with that same password
- **THEN** the sign-in succeeds according to the normal credential checks
