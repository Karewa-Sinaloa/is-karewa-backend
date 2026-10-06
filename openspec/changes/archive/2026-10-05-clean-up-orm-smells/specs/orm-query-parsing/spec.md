# Spec Delta

## Purpose

Defines how query-parameter filters are parsed into an operator and a value, so values that themselves contain colons are matched exactly as supplied.

## ADDED Requirements

### Requirement: Filter values containing colons are preserved
When a query parameter supplies an operator and value, the parser SHALL split on the first colon only, using the remainder as the value, so values that contain colons are not truncated.

#### Scenario: Value without a colon
- **WHEN** a client filters with an operator and a plain value (for example `eq:42`)
- **THEN** the filter uses the operator and the value `42`

#### Scenario: Value with a colon
- **WHEN** a client filters with a value that contains a colon (for example a time or URL)
- **THEN** the operator is taken from before the first colon
- **AND** the value keeps everything after the first colon intact

#### Scenario: Plain value defaults to equality
- **WHEN** a client supplies a value with no operator prefix
- **THEN** the filter uses equality and the supplied value

### Requirement: Delete association guards use the current record
When a module guards a delete against associated rows, the guard SHALL compare the association column against the current record's identifier, not an undefined value.

#### Scenario: Guardian targets the current record
- **WHEN** a delete is attempted for a record that has associated rows
- **THEN** the guard detects the association using the current record's identifier
- **AND** the delete is rejected while associations exist

#### Scenario: No associations
- **WHEN** a delete is attempted for a record with no associated rows
- **THEN** the guard allows the delete to proceed

### Requirement: One connection per request
The data layer SHALL reuse a single database connection across its helper calls within a request, while still returning an injected connection when one is provided.

#### Scenario: Repeated calls reuse the connection
- **WHEN** multiple data-layer helpers run in the same request
- **THEN** they share one connection instead of opening a new one each time

#### Scenario: Injected connection is respected
- **WHEN** a connection has been injected for tests
- **THEN** the layer returns that connection and does not open its own

### Requirement: Filter operators produce a single SQL shape
Each supported filter operator SHALL be handled by exactly one branch, producing one unambiguous SQL fragment.

#### Scenario: IN operator
- **WHEN** a filter uses the `IN` operator
- **THEN** the produced SQL uses a single `IN` clause with the supplied values
- **AND** no duplicate branch affects the result
