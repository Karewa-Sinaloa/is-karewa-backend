# Spec Delta

## MODIFIED Requirements

### Requirement: Mail settings can be overridden by the environment
The application SHALL allow the `mailing` settings — host, port, security, username, password, authentication, sender address, and sender name — to be overridden by documented environment variables, so a deployment or local environment can select a different mail target without editing `app/config.yml` or the `smtp_config` row. The precedence SHALL be environment variable, then the `smtp_config` row of the `config` table, then `app/config.yml`.

#### Scenario: Defined variable takes precedence
- **WHEN** a mail environment variable is present in the environment and the corresponding setting also exists in the `smtp_config` row or in `app/config.yml`
- **THEN** the application uses the environment value, including when that value is empty

#### Scenario: Undefined variable falls back to the file
- **WHEN** a mail environment variable is not present in the environment
- **THEN** the application uses the corresponding value from the `smtp_config` row when that row is present
- **AND** it uses the value from `app/config.yml` when the row is absent

#### Scenario: Authentication can be disabled for a local target
- **WHEN** the authentication environment variable is set to a falsey value such as empty, `0`, or `false`
- **THEN** the transport does not attempt authentication

#### Scenario: Overrides carry no committed secrets
- **WHEN** the overrides are used to select a local mail target
- **THEN** the values come from the untracked environment file and no mail credential is committed to the repository

## ADDED Requirements

### Requirement: Stored mail settings take precedence over the file
When no mail environment variable is set for a setting, the application SHALL prefer the value stored in the `smtp_config` row over the value declared in `app/config.yml`.

#### Scenario: Stored row wins over the file
- **WHEN** the `smtp_config` row and `app/config.yml` both define a mail setting and no environment variable defines it
- **THEN** the application uses the value from the row
