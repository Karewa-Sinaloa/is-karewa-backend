# Spec Delta

## Purpose

Defines how the API derives its public base URL constant from configuration, so
code that builds absolute API links receives a usable string.

## ADDED Requirements

### Requirement: API_URL is a URL string
The `API_URL` constant SHALL be a string that starts with a URL scheme and points
at the API base path, derived from the `api` configuration node, and SHALL NOT be
the `api` node object itself.

#### Scenario: Building an absolute API link
- **WHEN** application code concatenates a relative path onto `API_URL`
- **THEN** the result is a valid absolute URL string
- **AND** no "Object of class stdClass could not be converted to string" error is
  raised

#### Scenario: Scheme follows configuration
- **WHEN** the `api` configuration indicates HTTPS
- **THEN** `API_URL` uses the `https` scheme
- **AND** otherwise it uses `http`
