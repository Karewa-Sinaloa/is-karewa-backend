# orm-test-coverage Specification

## Purpose

Defines the automated coverage and the reusable harness that verify the shared data-access layer against an in-memory database, so the layer's behavior is checked without depending on a live MySQL server.

## Requirements

### Requirement: Layer is testable against a replaceable connection
The data-access layer SHALL allow its connection and connection configuration to be replaced for tests, and SHALL use MySQL by default when no replacement is supplied.

#### Scenario: Default connection unchanged
- **WHEN** the layer is used without an injected connection
- **THEN** it connects to MySQL using the configured constants

#### Scenario: Injected connection is used
- **WHEN** a test injects an in-memory database connection
- **THEN** layer operations run against that connection
- **AND** no MySQL connection is attempted

### Requirement: Test harness provides an isolated database
The test suite SHALL provide a harness that creates a fresh in-memory database with a known minimal schema and the constants the layer needs, so each test starts from a clean state.

#### Scenario: Fresh state per test
- **WHEN** a data-layer test starts
- **THEN** it has an empty in-memory database and the layer's required constants defined
- **AND** the prefix used by the layer is empty for the test

### Requirement: Data-layer behavior is covered by automated tests
The test suite SHALL cover query building, filter operators, value binding, and the create/read/update/delete operations on the portable paths, including the robustness behaviors defined for the layer.

#### Scenario: Query building and binding covered
- **WHEN** the suite runs
- **THEN** tests exercise field selection, filters, joins, ordering, and parameter binding
- **AND** a list query returns the expected rows from the in-memory database

#### Scenario: Robustness behaviors covered
- **WHEN** the suite runs
- **THEN** tests exercise error-code propagation, empty-collection handling, grouped counts, and transaction rollback
- **AND** each case asserts the behavior specified for the layer

### Requirement: Suite runs in the project's test environment
The project's test environment SHALL be able to run the data-layer tests, including under the project's standard test command.

#### Scenario: Running the suite
- **WHEN** the project's test command runs in the standard environment
- **THEN** the data-layer tests execute successfully
- **AND** no missing database-driver extension prevents them from running
