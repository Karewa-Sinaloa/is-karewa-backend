# Spec Delta

## Purpose

Defines the user columns the `access` module reads and writes for account
recovery and password reset, so every field resolves to a real column under the
configured database prefix.

## ADDED Requirements

### Requirement: Recovery writes the recovery-date column
The `access` module SHALL store the recovery code timestamp in the existing
`recovery_date` column and SHALL NOT reference a `recovery_datetime` column.

#### Scenario: Requesting a recovery code
- **WHEN** a client posts a known, active email to `POST /api/v5/access/recovery`
- **THEN** the user row is updated setting `recovery_date` and `recovery_code`
- **AND** no `Unknown column` database error is raised

### Requirement: Reset reads and clears the recovery-date column
The `access` module SHALL read the stored timestamp from `recovery_date` to check
expiration, and SHALL clear `recovery_date` and `recovery_code` when the password
is reset.

#### Scenario: Valid reset
- **WHEN** a client posts an email, a valid non-expired code, and a new password
  to `POST /api/v5/access/reset`
- **THEN** the password is updated and `recovery_date` and `recovery_code` are
  cleared
- **AND** the response uses the standard success envelope

#### Scenario: Expired code rejected
- **WHEN** the stored `recovery_date` plus the code time-to-live is in the past
- **THEN** the API rejects the request with the recovery-code expiry code
- **AND** no `Unknown column` database error is raised

### Requirement: Reset does not write non-existent columns
The `access` module SHALL only write columns that exist in the users table.

#### Scenario: Completing a reset
- **WHEN** a client completes a password reset
- **THEN** the update writes only existing columns (`password`,
  `recovery_date`, `recovery_code`)
- **AND** it does not attempt to write an `email_verified` column
