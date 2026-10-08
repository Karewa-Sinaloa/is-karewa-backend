# Spec Delta

## MODIFIED Requirements

### Requirement: Success responses document the envelope schema

Every success response SHALL declare a JSON schema of the response envelope (`message`, `code`, `http_code`, `data`, `meta`) and, for operations whose module method requires authentication, the conditional `allowed_roles` property, instead of a description alone.

#### Scenario: Reader inspects a list response

- **WHEN** a reader opens any documented `200` list response
- **THEN** the response declares a schema that describes the envelope fields

#### Scenario: Reader inspects an authenticated operation's schema

- **WHEN** a reader opens a documented success response of an operation that declares the bearer security scheme
- **THEN** the schema declares an `allowed_roles` object property whose `create`, `edit` and `delete` values are arrays of integer role IDs
- **AND** the schema describes the field as present only on authenticated responses

#### Scenario: Reader inspects an anonymous operation's schema

- **WHEN** a reader opens a documented success response of an operation with no security requirement
- **THEN** the schema does not declare an `allowed_roles` property

## ADDED Requirements

### Requirement: Contract sync covers the allowed roles disclosure
Regenerating the published OpenAPI document after the disclosure change SHALL yield a document that matches the published file, so the automated synchronization check passes with the `allowed_roles` property documented.

#### Scenario: Document regenerated with the new property

- **WHEN** the contract is regenerated from the current modules and the published document is compared
- **THEN** the check passes with `allowed_roles` documented on authenticated operations
