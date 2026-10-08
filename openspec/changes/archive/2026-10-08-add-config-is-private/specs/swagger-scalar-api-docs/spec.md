# Spec Delta

## MODIFIED Requirements

### Requirement: Documented fields match the module field map

The published OpenAPI document SHALL describe only the fields each module declares in its field map, so columns and keys removed from a module are no longer documented.

#### Scenario: Config module exposes the value field

- **WHEN** a reader inspects the documented `config` schema or its query parameters
- **THEN** the contract includes the `value` field
- **AND the contract does not mention a `data` or `public` field**

#### Scenario: Config module exposes the is_private field

- **WHEN** a reader inspects the documented `config` schema or its query parameters
- **THEN** the contract includes `is_private` as a boolean field with default `true`
- **AND the contract does not mention a `data` or `public` field**

### Requirement: Operation security matches module authentication declarations

Each documented operation SHALL carry the bearer security requirement if and only if the module declares that method as requiring authentication.

#### Scenario: Authenticated list operation

- **WHEN** a reader inspects an operation whose module method requires authentication (for example `GET /users`)
- **THEN** the operation declares the bearer security scheme

#### Scenario: Public write operation

- **WHEN** a reader inspects an operation whose module method allows anonymous access (for example `POST /users`)
- **THEN** the operation declares no security requirement

#### Scenario: Config item operation is public

- **WHEN** a reader inspects `GET /config/{id}`, whose module method allows anonymous access
- **THEN** the operation declares no security requirement

## ADDED Requirements

### Requirement: Row visibility rules are described in the contract
The list and item operations of the config module SHALL carry a description of the row visibility rule, so a reader knows that callers without roles 1, 2 or 3 only see entries with `is_private = 0` and receive `404000` for a private entry.

#### Scenario: Reader inspects the config list operation

- **WHEN** a reader opens the documented `GET /config` operation
- **THEN** the operation declares a description stating that private entries are excluded for callers without roles 1, 2 and 3

#### Scenario: Reader inspects the config item operation

- **WHEN** a reader opens the documented `GET /config/{id}` operation
- **THEN** the operation declares a description stating that a private entry answers `404000` for callers without roles 1, 2 and 3
