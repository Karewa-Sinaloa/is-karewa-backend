# API Responses

## Purpose

Describes how the Monitor Karewa API renders responses and where response codes live. Agents MUST route all output through the shared response helper.

## Requirements

### Requirement: Single response path
All responses SHALL go through `ApiResponse::Set()`, which writes the response payload and terminates execution with `die()`.

#### Scenario: Responding to a request
- **WHEN** any endpoint finishes, successfully or with an error
- **THEN** it responds through `ApiResponse::Set()`
- **AND** it does not print output directly

### Requirement: Response codes
Response codes SHALL live in `app/core/config/api_codes.yml`.

#### Scenario: Adding a response
- **WHEN** an endpoint needs a new response
- **THEN** it uses an existing code from `api_codes.yml` or adds one there

### Requirement: Response envelope
Every response SHALL include `meta.session_id`.

#### Scenario: Inspecting a response
- **WHEN** a client receives any response
- **THEN** the response includes `meta.session_id`

### Requirement: References
The response helper and its detailed contract SHALL be documented in `app/core/helpers/api_response.php` and `api_response.md`.

#### Scenario: Needing response details
- **WHEN** an agent needs the full response behavior
- **THEN** it reads `api_response.md` and `app/core/helpers/api_response.php`
