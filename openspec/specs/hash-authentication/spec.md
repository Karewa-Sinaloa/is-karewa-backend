# Hash Authentication

## Purpose

Defines how a link/webhook token is minted, bound to a resource payload, expires, and is validated from an incoming request, so alternative hash authentication is safe to enable.

## Requirements

### Requirement: Tokens are bound to a payload
A token SHALL be minted over a payload that identifies the resource it grants access to, and validation SHALL only succeed when presented with that same payload.

#### Scenario: Matching payload
- **WHEN** a token is validated with the same payload it was minted for
- **THEN** validation succeeds

#### Scenario: Different payload
- **WHEN** a token minted for one payload is validated against a different payload
- **THEN** validation fails

### Requirement: Tokens expire
A token SHALL carry an expiration, and validation SHALL reject a token whose expiration has passed.

#### Scenario: Within the validity window
- **WHEN** a token is validated before its expiration
- **THEN** validation succeeds

#### Scenario: Expired token
- **WHEN** a token is validated after its expiration
- **THEN** validation fails

### Requirement: Witness is read from the request header with a query fallback
Validation SHALL read the token witness from a request header, and SHALL accept the legacy `_key` query parameter when no header witness is present.

#### Scenario: Header provided
- **WHEN** a request carries the witness in a header
- **THEN** validation uses the header witness

#### Scenario: Legacy query parameter
- **WHEN** a request carries no header witness but includes `_key`
- **THEN** validation uses the `_key` value

#### Scenario: No witness
- **WHEN** a request carries neither a header witness nor `_key`
- **THEN** validation fails

### Requirement: A module can opt into hash authentication
When a module declares alternative hash authentication for a method, the access check SHALL validate the supplied hash and grant access when it is valid, without requiring a session token.

#### Scenario: Valid alternative hash
- **WHEN** a method declares hash authentication and the request presents a valid hash
- **THEN** access is granted
- **AND** no session token is required

#### Scenario: Invalid alternative hash
- **WHEN** a method declares hash authentication and the presented hash is invalid or missing
- **THEN** access is denied

### Requirement: Minting and validation agree on encoding
The token minted by `Create` SHALL validate under `Validate` for the same payload, using one consistent encoding on both sides.

#### Scenario: Round trip
- **WHEN** a token is minted for a payload and immediately validated for that payload
- **THEN** validation succeeds
