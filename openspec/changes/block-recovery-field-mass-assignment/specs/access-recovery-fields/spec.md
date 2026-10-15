# Spec Delta

## ADDED Requirements

### Requirement: Recovery columns are not writable from client payloads
The users module SHALL ignore `recovery_code` and `recovery_date` present in client payloads of its create and update operations: submitted values SHALL NOT be persisted, SHALL NOT overwrite stored values, and SHALL NOT change the response.

#### Scenario: Create payload with recovery fields is ignored
- **WHEN** a client creates a user sending `recovery_code` and `recovery_date` in the payload
- **THEN** the created row has both recovery columns unset
- **AND** the response is the normal success response for a created user

#### Scenario: Update payload with recovery fields is ignored
- **WHEN** a client updates a user sending `recovery_code` and `recovery_date` in the payload
- **THEN** the stored recovery values remain exactly as they were before the update
- **AND** the response is the normal success response for an updated user

### Requirement: Only the access recovery flow writes the recovery columns
Only the account-recovery flow of the `access` module SHALL modify `recovery_code` and `recovery_date`; every other write path SHALL leave them untouched, so a client can never direct or override a recovery code through another module.

#### Scenario: Users-module writes leave recovery values intact
- **WHEN** a recovery code has been issued through the access flow and a client then performs a users-module create or update including the recovery columns
- **THEN** the recovery columns keep the values written by the access flow

#### Scenario: Recovery flow remains the only writer
- **WHEN** a client requests account recovery for an email with an active account
- **THEN** that flow is what sets `recovery_code` and `recovery_date`

### Requirement: Stored recovery codes are always hashed
The persisted `recovery_code` SHALL always be a one-way hash produced by the access recovery flow; a plaintext recovery value submitted through any client payload SHALL NOT be persisted in any circumstance.

#### Scenario: Plaintext sent through the API is never stored
- **WHEN** a client sends a plaintext recovery code in a users-module create or update payload
- **THEN** no plaintext recovery value is persisted
- **AND** the response contains no recovery field

#### Scenario: The access flow stores a hash, not the issued code
- **WHEN** the access flow issues a recovery code
- **THEN** the value stored in `recovery_code` differs from the issued code
- **AND** the issued code cannot be read back from the stored value
