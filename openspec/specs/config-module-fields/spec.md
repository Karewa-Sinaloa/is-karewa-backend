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
