# Configuration And Logging

## Purpose

Describes how the Monitor Karewa backend loads configuration and writes logs. Agents MUST follow these rules when reading configuration or logging.

## Requirements

### Requirement: Application configuration
`app/config.yml` SHALL be gitignored. Encrypted configuration SHALL be handled through `app/config.yml.secret` and git-secret. Runtime constants SHALL be loaded in `app/core/config/base.php`.

#### Scenario: Adding configuration
- **WHEN** an agent needs a new configuration value
- **THEN** it adds it to the configuration and reads it through the runtime constants in `app/core/config/base.php`
- **AND** it does not commit secrets

### Requirement: Global runtime values
The application SHALL expose the global runtime values `$_config`, `$_payload`, and `$_apiConfig`.

#### Scenario: Reading the request payload
- **WHEN** a module needs the request body
- **THEN** it reads `$_payload`

### Requirement: Configuration sections
The main configuration SHALL include the sections `database`, `jwt`, `session`, `log`, `cors`, `statics`, `mailings`, `hcaptcha`, `uploads`, `valid_requests`, and related settings.

#### Scenario: Locating a setting
- **WHEN** an agent needs a setting
- **THEN** it looks in the corresponding configuration section

### Requirement: Logging
Code SHALL log errors with `error_logs([$context, $code, $message, __LINE__, __FILE__])`. Log file locations SHALL be configured through `app/config.yml`, and runtime logs SHALL live under `logs/`.

#### Scenario: Logging an error
- **WHEN** code encounters an error
- **THEN** it calls `error_logs()` with the documented argument shape
- **AND** the entry lands under `logs/` as configured

### Requirement: Mail settings can be overridden by the environment
The application SHALL allow the `mailing` settings — host, port, security, username, password, authentication, sender address, and sender name — to be overridden by documented environment variables, so a deployment or local environment can select a different mail target without editing `app/config.yml`.

#### Scenario: Defined variable takes precedence
- **WHEN** a mail environment variable is present in the environment and the corresponding setting also exists in `app/config.yml`
- **THEN** the application uses the environment value, including when that value is empty

#### Scenario: Undefined variable falls back to the file
- **WHEN** a mail environment variable is not present in the environment
- **THEN** the application uses the corresponding value from `app/config.yml`

#### Scenario: Authentication can be disabled for a local target
- **WHEN** the authentication environment variable is set to a falsey value such as empty, `0`, or `false`
- **THEN** the transport does not attempt authentication

#### Scenario: Overrides carry no committed secrets
- **WHEN** the overrides are used to select a local mail target
- **THEN** the values come from the untracked environment file and no mail credential is committed to the repository
