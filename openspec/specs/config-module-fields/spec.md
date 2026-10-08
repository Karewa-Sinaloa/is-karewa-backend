# config-module-fields Specification

## Purpose

Defines the field map the `config` module exposes over its key/value table, so reads, writes, and search resolve only columns that exist.

## Requirements

### Requirement: Configuration values map to the value column
The `config` module SHALL expose the stored configuration payload through a field backed by the `value` column, and SHALL NOT reference a `data` column.

#### Scenario: Reading a configuration entry
- **WHEN** an authorized client requests `GET /api/v5/config/{id}`
- **THEN** the response includes the entry's `value`
- **AND** the query does not reference a non-existent `data` column

#### Scenario: Writing a configuration entry
- **WHEN** an authorized client creates or updates a configuration entry with a `value`
- **THEN** the value is persisted to the `value` column

### Requirement: No field references the removed public column
The `config` module SHALL NOT declare, validate, or search a `public` field, because the table has no such column.

#### Scenario: Searching configuration entries
- **WHEN** an authorized client requests `GET /api/v5/config?search=...`
- **THEN** the query searches only existing columns (`name`, `slug`) and does not fail

#### Scenario: No public field is exposed or validated
- **WHEN** a configuration entry is read or written
- **THEN** neither the response nor the validation references a `public` field

### Requirement: Configuration entries carry an is_private flag
The `config` module SHALL store a per-row `is_private` flag in a column of the `config` table that defaults to `1`, SHALL expose it as a readable, filterable and writable field, and SHALL persist `0` when a client marks an entry public.

#### Scenario: New entry defaults to private
- **WHEN** a client creates a configuration entry without sending `is_private`
- **THEN** the stored row has `is_private = 1`

#### Scenario: Entry created as public
- **WHEN** a client with role 1, 2 or 3 creates a configuration entry sending `is_private = 0`
- **THEN** the stored row has `is_private = 0`

#### Scenario: Existing entry is switched to public
- **WHEN** a client with role 1, 2 or 3 sends `is_private = 0` when updating an entry
- **THEN** the stored row keeps `0` instead of falling back to the default

#### Scenario: Flag is exposed to readers
- **WHEN** a client lists entries or reads an entry
- **THEN** the response includes `is_private`
- **AND** filtering by `is_private` selects only rows with that flag

### Requirement: Private entries are visible only to roles 1, 2 and 3
Entries with `is_private = 1` SHALL be returned only to callers authenticated with role 1, 2 or 3. For any other caller they SHALL be absent from listings and from pagination counts, and an item request for one of them SHALL respond `404000` as if the id did not exist.

#### Scenario: Anonymous listing omits private entries
- **WHEN** an unauthenticated client lists configuration entries while private entries exist
- **THEN** the response contains only entries with `is_private = 0`
- **AND** the pagination count matches the returned rows

#### Scenario: Anonymous item request for a private entry
- **WHEN** an unauthenticated client requests a private entry by id
- **THEN** the response is `404000` (`APP_RESULTS_NOT_FOUND`)

#### Scenario: Administrative role sees private entries
- **WHEN** a client authenticated with role 1, 2 or 3 lists configuration entries
- **THEN** private and public entries are both returned

#### Scenario: Lower role does not see private entries
- **WHEN** a client authenticated with role 4 or 5 requests a private entry by id
- **THEN** the response is `404000` (`APP_RESULTS_NOT_FOUND`)

### Requirement: Public entries are readable by everyone and writable by roles 1, 2 and 3
Entries with `is_private = 0` SHALL be readable by any caller, including anonymous ones, while creating, updating and deleting configuration entries SHALL remain restricted to roles 1, 2 and 3.

#### Scenario: Anonymous reads a public entry
- **WHEN** an unauthenticated client requests a public entry by id
- **THEN** the response carries the entry with `200`

#### Scenario: Anonymous write is rejected
- **WHEN** an unauthenticated client sends a create, update or delete request to the config module
- **THEN** the request is rejected with `901004` before the method runs

#### Scenario: Lower role cannot write
- **WHEN** a client authenticated with role 4 updates a public entry
- **THEN** the request is rejected with `901004` before the method runs

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
