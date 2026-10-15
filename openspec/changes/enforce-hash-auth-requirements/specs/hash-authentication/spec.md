# Spec Delta

## ADDED Requirements

### Requirement: A method can be declared hash-required
A module SHALL be able to declare a method as hash-required. When a method is hash-required, a request SHALL be denied unless it presents a valid hash, even when the method does not require session authentication. A valid hash SHALL grant access without a session token.

#### Scenario: Hash-required endpoint without a witness
- **WHEN** a client calls a hash-required method with no hash witness
- **THEN** the request is denied with the standard access-denied response
- **AND** the method's own output is not produced

#### Scenario: Hash-required endpoint with an invalid hash
- **WHEN** a client calls a hash-required method with a hash that fails validation
- **THEN** the request is denied with the standard access-denied response

#### Scenario: Hash-required endpoint with a valid hash
- **WHEN** a client calls a hash-required method with a valid hash
- **THEN** access is granted without a session token
- **AND** the method's output is produced

### Requirement: Declared payload matches the payload of minted links
The payload a module declares for a method SHALL be the same payload the server uses when it mints links for that method, so that a link the server issues always validates against the endpoint it points to.

#### Scenario: Server-minted link validates
- **WHEN** the server mints a link for a hash-required endpoint and a client presents that link's hash
- **THEN** validation succeeds

#### Scenario: Link minted for a different resource
- **WHEN** a hash minted for one resource is presented to an endpoint whose declared payload identifies a different resource
- **THEN** validation fails

### Requirement: Declarations the server cannot mint provide no hash control
A hash declaration whose payload the server never mints SHALL NOT be treated as hash protection: it grants no access and, on a hash-required method, it denies every request. Modules SHALL declare only real payloads the server can emit, and placeholder markers SHALL NOT be relied upon as access control.

#### Scenario: Placeholder marker on a method
- **WHEN** a method declares a placeholder payload that the server never mints for any link
- **THEN** that declaration does not make the method hash-protected
- **AND** a hash-required method with such a declaration denies requests until a real payload is declared

#### Scenario: Coverage of declared payloads
- **WHEN** the declared hash payloads of the modules are checked
- **THEN** each declared payload corresponds to a payload the server mints for real links
