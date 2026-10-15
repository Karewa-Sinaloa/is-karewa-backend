# Spec Delta

## ADDED Requirements

### Requirement: Runtime secrets come from the environment or the git-secret reveal flow
The runtime values of the database credentials, the mail provider API key, the hCaptcha secret, and the HMAC hash-auth secret SHALL be supplied by documented environment variables, following the same overlay pattern already used for mail settings, with the git-secret reveal flow (`app/config.yml.secret`) as supported fallback. When a secret's environment variable is present, it SHALL take precedence over any value in the configuration file, and when it is absent the revealed file value SHALL be used.

#### Scenario: Defined variable takes precedence
- **WHEN** an environment variable carrying one of these secrets is present and the configuration file also carries a value for the same secret
- **THEN** the application uses the environment value

#### Scenario: Absent variable falls back to the revealed file
- **WHEN** an environment variable for one of these secrets is not present in the environment
- **THEN** the application uses the value revealed by the git-secret flow in the configuration file

#### Scenario: Secret unavailable from both sources
- **WHEN** neither the environment variable nor the revealed configuration file provides a required secret
- **THEN** the operation that requires the secret fails with a configuration error instead of using a default or placeholder value

### Requirement: Configuration files carry no real secret values
No configuration file present in the working tree or committed to the repository SHALL contain real secret values for the database credentials, the mail provider API key, the hCaptcha secret, or the HMAC hash-auth secret. Tracked examples and templates SHALL contain only placeholders, and real values SHALL reach the runtime only through the environment or the git-secret reveal flow.

#### Scenario: Inspecting tracked and working-tree configuration
- **WHEN** the repository and the working tree are inspected for configuration files
- **THEN** no real database credential, provider API key, hCaptcha secret, or HMAC secret appears in any tracked file or in the working-tree configuration
- **AND** tracked example files carry only placeholder values

#### Scenario: Environment documents the secret variables
- **WHEN** the documented environment example is consulted for a deployment
- **THEN** it lists every secret variable the application reads, with placeholder values only
