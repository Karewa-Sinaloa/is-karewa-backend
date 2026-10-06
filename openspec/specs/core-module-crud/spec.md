# core-module-crud Specification

## Purpose

Defines how the built-in `roles` and `config` modules handle their declared requests and CRUD operations, so they respond through the API's standard response envelope instead of terminating with an unhandled PHP error.

## Requirements

### Requirement: Built-in modules dispatch without unhandled errors
The API SHALL process requests to the built-in `roles` and `config` modules and return a response through the standard JSON envelope, never terminating with an unhandled PHP error.

#### Scenario: Roles request is dispatched
- **WHEN** a client sends a supported `/api/v5/roles` request
- **THEN** the API returns a JSON response carrying a documented `code` and a matching HTTP status
- **AND** no unhandled PHP error occurs

#### Scenario: Config request is dispatched
- **WHEN** a client sends a supported `/api/v5/config` request
- **THEN** the API returns a JSON response carrying a documented `code` and a matching HTTP status
- **AND** no unhandled PHP error occurs

### Requirement: Role module initializes from request context
The role module SHALL initialize from the request context without a type error, so any supported method can be dispatched.

#### Scenario: Initialization for any supported method
- **WHEN** the role module is loaded for a supported request
- **THEN** initialization completes without a type error
- **AND** the requested method is dispatched

### Requirement: Configuration records use the standard delete flow
Deleting a configuration record SHALL follow the same behavior as other CRUD modules, with no association guard: it responds with the standard delete outcome when an entry id is provided and with the no-id error when it is not.

#### Scenario: Delete an existing configuration record
- **WHEN** an authorized client sends `DELETE /api/v5/config/{id}`
- **THEN** the API deletes the record and responds with the delete success code (`DELETED`) or `NOCHANGE` when nothing changed

#### Scenario: Delete without an entry id
- **WHEN** a client sends `DELETE /api/v5/config` without an entry id
- **THEN** the API responds with `400002` (`API_NO_ENTRY_ID_PROVIDED`)

#### Scenario: No association guard blocks configuration deletion
- **WHEN** a configuration record is deleted
- **THEN** the deletion is not blocked by an association check on any other table

### Requirement: Failures are reported through the standard error envelope
When an operation cannot complete, the API SHALL report it with a documented error code and matching HTTP status rather than an unhandled PHP error.

#### Scenario: Downstream failure
- **WHEN** a supported `roles` or `config` request fails downstream
- **THEN** the API responds with a documented error `code` and a matching HTTP status
