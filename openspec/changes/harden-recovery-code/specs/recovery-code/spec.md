# Spec Delta

## Purpose

Governs the generation, verification and lifecycle of account-recovery codes so they are cryptographically strong, attempt-limited and single-use within their validity window.

## ADDED Requirements

### Requirement: Recovery codes are generated with a cryptographically secure source
A recovery code SHALL be generated from a cryptographically secure random source. Non-cryptographic pseudo-random generators SHALL NOT be used, and observing previously issued codes SHALL NOT make future codes predictable.

#### Scenario: Consecutive recovery requests produce different codes
- **WHEN** a client requests recovery twice for the same account (each request replacing the previous code)
- **THEN** the two issued codes are different
- **AND** neither code can be derived from the other or from previously issued codes

#### Scenario: No non-cryptographic generator is used
- **WHEN** the recovery flow generates a code
- **THEN** the generator used provides cryptographically secure randomness

### Requirement: Failed verification attempts are limited
Failed verification attempts SHALL be counted per recovery code (per account), and after at most 5 failed attempts the code SHALL be invalidated: subsequent verification attempts SHALL fail even if the submitted code is the correct one. Requesting a new recovery code SHALL reset the attempt count.

#### Scenario: Brute force is stopped after five failures
- **WHEN** five wrong codes are submitted for the same recovery code
- **THEN** the code is invalidated
- **AND** a later attempt with the original correct code is rejected as invalid

#### Scenario: A correct code within the attempt limit still works
- **WHEN** fewer than five wrong codes have been submitted and the correct code is then presented within the validity window
- **THEN** verification succeeds and the reset proceeds

#### Scenario: Requesting a new code resets the counter
- **WHEN** the account holder requests a new recovery code after reaching the attempt limit
- **THEN** the new code starts with a fresh attempt count

### Requirement: A recovery code is single-use
A recovery code SHALL be invalidated after it is successfully used, and SHALL NOT be usable for any further reset.

#### Scenario: Reuse after a successful reset is rejected
- **WHEN** a client successfully resets a password with a code and then submits the same code again for another reset
- **THEN** the second attempt is rejected as an invalid code

#### Scenario: A new recovery request replaces the previous code
- **WHEN** a client requests a new recovery code after one was already issued
- **THEN** the previously issued code is no longer valid

### Requirement: Recovery codes expire after the configured time-to-live
A recovery code SHALL expire after the configured time-to-live (3600 s) measured from issuance, and an expired code SHALL be rejected regardless of the remaining attempt count.

#### Scenario: Expired code rejected
- **WHEN** a code is presented after its time-to-live has elapsed
- **THEN** the verification fails with the code-expired error
- **AND** the password is not changed
