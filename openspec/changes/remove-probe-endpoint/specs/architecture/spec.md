# Spec Delta

## ADDED Requirements

### Requirement: Published web root contains only required application files
The published web root SHALL contain only the application entry point(s), static assets, error pages and documentation the application requires, and SHALL NOT contain diagnostic or probe scripts. Diagnostic information about the incoming request (remote client address, proxy headers) SHALL be exposed only through authenticated endpoints or an internal healthcheck endpoint, never through a public unauthenticated script.

#### Scenario: Web root inventory
- **WHEN** the contents of the published web root are enumerated
- **THEN** only the application entry point(s), static assets, error pages and documentation are present
- **AND** no diagnostic or probe script exists in the web root

#### Scenario: Diagnostic script is requested
- **WHEN** a client requests a diagnostic or probe script in the web root
- **THEN** the response discloses no remote client address and no proxy header value

#### Scenario: Reintroduction of a probe script is caught
- **WHEN** a change adds a diagnostic script, or a publicly reachable script that reveals request topology details, to the web root
- **THEN** the automated verification of the web root fails

#### Scenario: Operator needs request topology diagnostics
- **WHEN** an operator needs diagnostic information about requests (remote addresses, proxy headers)
- **THEN** that information is available only through an authenticated endpoint or the internal healthcheck endpoint
