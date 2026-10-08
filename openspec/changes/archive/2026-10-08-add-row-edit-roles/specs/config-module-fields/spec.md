# Spec Delta

## ADDED Requirements

### Requirement: Rows declare which roles may modify them
Each `config` row SHALL carry an `edit_roles` list of role ids that are allowed to modify that row, defaulting to `1,2,3`, and a write request SHALL be rejected with `901009` (`APP_AUTH_ROW_FORBIDDEN`) when the caller's role is not in the list. The role 1 SHALL always be allowed, even when it is absent from the list, so a row can never become unmodifiable.

#### Scenario: Row editable by every configured role
- **WHEN** a client authenticated with role 2 updates or deletes a row whose list is `1,2,3`
- **THEN** the operation is applied

#### Scenario: Row restricted to a subset rejects other roles
- **WHEN** a client authenticated with role 3 updates or deletes a row whose list is `1,2`
- **THEN** the response is `901009` (`APP_AUTH_ROW_FORBIDDEN`)
- **AND** the stored row is unchanged

#### Scenario: Administrator always retains edit access
- **WHEN** a client authenticated with role 1 updates a row whose list is `2`
- **THEN** the operation is applied

#### Scenario: New row starts editable by roles 1, 2 and 3
- **WHEN** a client creates a configuration entry
- **THEN** the stored row has an `edit_roles` list of `1,2,3`

### Requirement: The permission list is readable but not writable through the API
The `edit_roles` list SHALL appear in `config` responses and SHALL be usable as a query filter, and the `config` module SHALL NOT accept it in create or update payloads: writing it remains a database-side operation.

#### Scenario: List is returned and filterable
- **WHEN** a client lists or reads configuration entries
- **THEN** the response includes `edit_roles`
- **AND** filtering by `edit_roles` selects only rows whose list contains that role

#### Scenario: Client cannot change the list
- **WHEN** a client sends `edit_roles` in a create or update payload
- **THEN** the response succeeds
- **AND** the stored list is unchanged
