# Spec Delta

## Purpose

Authentication and recovery flows respond identically, in content and in timing, whether or not the requested account exists, so that neither response bodies nor response times disclose which emails have registered accounts.

## ADDED Requirements

### Requirement: Recovery responses are identical for every email address
The recovery endpoint SHALL return the same response — same status code, same message and the same fields — when the submitted email belongs to an unknown account, to an inactive account, or to an active account, and SHALL NOT disclose the difference in any part of the response envelope.

#### Scenario: Unknown email gets the same response as a known email
- **WHEN** a client requests recovery for an email address with no account
- **THEN** the response is identical in code, message and fields to the response for an email address with an active account
- **AND** no message is delivered to an address with no active account

#### Scenario: Inactive account gets the same response as an active account
- **WHEN** a client requests recovery for an email address whose account is inactive
- **THEN** the response is identical in code, message and fields to the response for an active account
- **AND** no message is delivered to an address with no active account

### Requirement: Recovery responses do not reveal account existence by timing
Every recovery response SHALL observe the same minimum response time, and the path for an unknown or inactive account SHALL perform verification work of equivalent cost to the known-account path, so that response latency does not disclose whether the account exists.

#### Scenario: Unknown email is not answered faster
- **WHEN** a client requests recovery for an email address with no account and measures the response time
- **THEN** the response arrives no sooner than the minimum response time observed for a known account
- **AND** the endpoint performed verification work of equivalent cost before responding

#### Scenario: Known email observes the same minimum
- **WHEN** a client requests recovery for an email address with an active account and measures the response time
- **THEN** the response arrives no sooner than the same minimum response time

### Requirement: Login responses do not reveal account existence by timing
Login SHALL perform credential verification work of equivalent cost whether or not the submitted email has an account, so that responses to credentials for a missing account are not measurably faster than responses to credentials for an existing account.

#### Scenario: Login for an unknown email takes equivalent time
- **WHEN** a client submits syntactically valid credentials for an email with no account
- **THEN** the response time is equivalent to the response time for an existing account with a wrong password
- **AND** both responses carry the same authentication-failed error

#### Scenario: Login for an existing account is unchanged
- **WHEN** a client submits correct credentials for an active account
- **THEN** login succeeds with the session response as before

### Requirement: No response field discloses account existence
No field of any authentication or recovery response — message, error code, validation detail or metadata — SHALL vary according to whether the submitted account exists, except for the session data returned to the account holder after successful authentication.

#### Scenario: Error responses match for unknown and existing accounts
- **WHEN** a client submits a wrong password for an email with no account and for an email with an existing account
- **THEN** both responses are identical in code, message and fields

#### Scenario: Validation errors do not depend on existence
- **WHEN** a client submits an invalid payload to the recovery endpoint for an unknown email and for a known email
- **THEN** both responses report the same validation errors
