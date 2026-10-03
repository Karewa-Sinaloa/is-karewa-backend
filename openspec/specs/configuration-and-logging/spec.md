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
The main configuration SHALL include the sections `database`, `jwt`, `session`, `log`, `cors`, `statics`, `mailings`, `facebook`, `hcaptcha`, `uploads`, `valid_requests`, and related settings.

#### Scenario: Locating a setting
- **WHEN** an agent needs a setting
- **THEN** it looks in the corresponding configuration section

### Requirement: Logging
Code SHALL log errors with `error_logs([$context, $code, $message, __LINE__, __FILE__])`. Log file locations SHALL be configured through `app/config.yml`, and runtime logs SHALL live under `logs/`.

#### Scenario: Logging an error
- **WHEN** code encounters an error
- **THEN** it calls `error_logs()` with the documented argument shape
- **AND** the entry lands under `logs/` as configured
