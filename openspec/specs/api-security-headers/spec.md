# api-security-headers Specification

## Purpose
Defines the cross-origin policy and the security response headers the API emits on every response.

## Requirements

### Requirement: Explicit origin allow-listing
The API SHALL allow cross-origin requests only from a configured allow-list of origins, and SHALL reject requests from origins not on the list with a proper HTTP error response.

#### Scenario: Allowed origin
- **WHEN** a request arrives from an origin on the allow-list
- **THEN** the response grants that origin access

#### Scenario: Disallowed origin
- **WHEN** a request arrives from an origin not on the allow-list
- **THEN** the response denies access with a proper HTTP error status
- **AND** it is not a bare JSON body without a status code

#### Scenario: Development wildcard
- **WHEN** the environment is configured to allow any origin
- **THEN** the wildcard SHALL NOT be combined with credential support

### Requirement: Credentials are not combined with a wildcard origin
The API SHALL NOT send `Access-Control-Allow-Credentials: true` together with a wildcard `Access-Control-Allow-Origin`.

#### Scenario: Credentialed request
- **WHEN** the API grants credential support for an allowed origin
- **THEN** the granted origin is the specific requesting origin, not `*`

### Requirement: Security headers are emitted
Every API response SHALL include standard security headers: a nosniff content-type option, a framing policy, and a referrer policy. When the site is served over HTTPS, it SHALL also emit HTTP Strict Transport Security.

#### Scenario: Any response
- **WHEN** the API responds to a request
- **THEN** the response includes the nosniff, framing, and referrer-policy headers

#### Scenario: HTTPS response
- **WHEN** the API is served over HTTPS
- **THEN** the response includes a Strict-Transport-Security header
