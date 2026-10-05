# Spec Delta

## ADDED Requirements

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
