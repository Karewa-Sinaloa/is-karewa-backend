# Spec Delta

## MODIFIED Requirements

### Requirement: Extra data does not override core envelope fields
When extra data is supplied, the API SHALL attach it to the envelope without replacing the reserved fields `code`, `http_code`, `message`, `meta`, or `allowed_roles`.

#### Scenario: Data key collides with a reserved field
- **WHEN** extra data contains a `code`, `http_code`, `message`, `meta`, or `allowed_roles` key
- **THEN** the envelope's reserved fields keep their catalog values or their computed value for `allowed_roles`
- **AND** other data keys are attached
