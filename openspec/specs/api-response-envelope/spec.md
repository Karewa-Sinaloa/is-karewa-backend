# API Response Envelope

## Purpose

Defines how the API renders its JSON response envelope for a known or unknown response code, including the internal-error fallback and response metadata.

## Requirements

### Requirement: Known codes render their documented envelope
When given a code present in the response code catalog, the API SHALL respond with that code's message, symbolic code, and HTTP status, including the response metadata.

#### Scenario: Success code
- **WHEN** the API responds with a known success code such as `SUCCESS`
- **THEN** the response carries the documented message, code, and HTTP status
- **AND** the response includes `meta.session_id`

### Requirement: Unknown codes fall back to the internal-error envelope
When given a code absent from the catalog, or when the catalog cannot be read or parsed, the API SHALL respond with the internal-error envelope (`APP_INTERNAL_SERVER_ERROR`, HTTP 500) instead of raising an unhandled error.

#### Scenario: Unknown code
- **WHEN** the API responds with a code that is not defined in the catalog
- **THEN** the response is well-formed JSON with code `APP_INTERNAL_SERVER_ERROR` and HTTP status 500
- **AND** no unhandled PHP error occurs

#### Scenario: Catalog unreadable or unparsable
- **WHEN** the catalog file is missing or cannot be parsed
- **THEN** the response is well-formed JSON with code `APP_INTERNAL_SERVER_ERROR` and HTTP status 500
- **AND** no unhandled PHP error occurs

### Requirement: Extra data does not override core envelope fields
When extra data is supplied, the API SHALL attach it to the envelope without replacing the reserved fields `code`, `http_code`, `message`, or `meta`.

#### Scenario: Data key collides with a reserved field
- **WHEN** extra data contains a `code`, `http_code`, `message`, or `meta` key
- **THEN** the envelope's reserved fields keep their catalog values
- **AND** other data keys are attached
