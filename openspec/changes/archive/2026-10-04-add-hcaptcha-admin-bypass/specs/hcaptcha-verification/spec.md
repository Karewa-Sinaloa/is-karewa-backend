# Spec Delta

## Purpose

Defines how the login flow verifies an hCaptcha token and how it behaves when the upstream verification service returns an unsuccessful or malformed response.

## ADDED Requirements

### Requirement: Login verifies the hCaptcha token
Login SHALL require a captcha token and SHALL verify it against the configured hCaptcha verification service before a session is issued.

#### Scenario: Valid captcha token
- **WHEN** login receives a captcha token that the verification service accepts
- **THEN** login proceeds to credential verification

#### Scenario: Missing captcha token
- **WHEN** login is called without a captcha token
- **THEN** login is rejected with the validation error response
- **AND** no session is issued

#### Scenario: Rejected captcha token
- **WHEN** login receives a captcha token that the verification service rejects
- **THEN** login is rejected with the third-party error response code
- **AND** no session is issued

### Requirement: Captcha verification fails closed
Captcha verification SHALL only succeed on a well-formed success response from the verification service. A non-success, empty, or malformed response, or a transport failure, SHALL result in rejection with the third-party error response code and SHALL NOT raise an unhandled error or grant access.

#### Scenario: Malformed upstream response
- **WHEN** the verification service returns a body that cannot be parsed as a success payload
- **THEN** login is rejected with the third-party error response code
- **AND** the request does not fail with an unhandled server error

#### Scenario: Upstream transport failure
- **WHEN** the request to the verification service fails at the transport level
- **THEN** login is rejected with the third-party error response code
- **AND** no session is issued

#### Scenario: Non-success verification result
- **WHEN** the verification service returns a response whose success flag is not true
- **THEN** login is rejected with the third-party error response code
