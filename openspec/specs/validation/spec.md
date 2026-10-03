# Validation

## Purpose

Describes how input validation is declared and applied in the Monitor Karewa backend. Agents MUST follow this when adding or modifying validation rules.

## Requirements

### Requirement: Rules are pipe-separated strings
Validation rules SHALL be stored as pipe-separated strings in the module's `$rules` array.

#### Scenario: Declaring a rule
- **WHEN** an agent adds validation to a field
- **THEN** it writes the rules as a pipe-separated string in `$rules`

### Requirement: Supported rules
The validator SHALL support at least the following rules: `required`, `max`, `email`, `unique:table:column`, `exist:table:column`, `numeric`, `alpha`, `alpha_dash`, `alpha_spaces`, `base64`, `date_format`, `time_format`, `decimal`, `rfc`, `url`, `boolean`, and `json`.

#### Scenario: Using a table rule
- **WHEN** a rule references a table (`unique:` or `exist:`)
- **THEN** it names the table and column it checks against

### Requirement: Validator contract
`FieldsValidator::Validation($fields, $rules, $id)` SHALL return `array|false`. On updates, callers SHALL pass the record `$id` so `unique` can ignore the current record.

#### Scenario: Update with a unique field
- **WHEN** a record is updated with a `unique` rule
- **THEN** the validator ignores the record's own value for that field
- **AND** returns `false` when validation passes
