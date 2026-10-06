# orm-data-layer Specification

## Purpose

Defines how the shared data-access layer reports errors, handles empty result sets, counts grouped results, and manages transaction boundaries, so modules can rely on consistent data-layer behavior.

## Requirements

### Requirement: Errors preserve their domain code
When a database operation fails with an application error that already carries a domain code, the data layer SHALL propagate that code unchanged. Genuine SQL/driver failures SHALL be reported with a distinct database code rather than being collapsed into the generic application error.

#### Scenario: Domain error propagates
- **WHEN** an operation fails with an application error carrying a domain code (for example an association guard rejection)
- **THEN** the raised error keeps that domain code
- **AND** the generic application error code is not substituted

#### Scenario: SQL failure reported distinctly
- **WHEN** a statement fails because of a SQL syntax, constraint, or driver error
- **THEN** the raised error uses a distinct database error code
- **AND** the original message is retained for logging

### Requirement: Empty collections are not errors
An `index`/`list` read that finds no rows SHALL return the standard success envelope with an empty `data` array. A `show` read for a missing single record SHALL continue to report not-found.

#### Scenario: Empty collection
- **WHEN** an authorized client lists a collection with no matching rows
- **THEN** the response is successful
- **AND** `data` is an empty array
- **AND** the response is not a not-found error

#### Scenario: Missing single record
- **WHEN** an authorized client requests a single record that does not exist
- **THEN** the response reports not-found

### Requirement: Counts honour grouping
A `count` query that specifies `GROUP BY` SHALL return the number of groups, matching the number of rows the equivalent grouped list query would return.

#### Scenario: Grouped count
- **WHEN** a count is requested with one or more `GROUP BY` columns
- **THEN** the result equals the number of distinct groups
- **AND** it does not reflect only the first group

#### Scenario: Ungrouped count
- **WHEN** a count is requested without grouping
- **THEN** the result is the total number of matching rows

### Requirement: Multi-step writes are atomic
A database operation composed of more than one statement SHALL run inside a transaction and SHALL roll back so no partial changes persist when any step fails.

#### Scenario: Failure rolls back
- **WHEN** a multi-step write fails partway through
- **THEN** all statements from that operation are rolled back
- **AND** no partial change is persisted

#### Scenario: Success commits
- **WHEN** a multi-step write completes successfully
- **THEN** all of its statements are committed together
