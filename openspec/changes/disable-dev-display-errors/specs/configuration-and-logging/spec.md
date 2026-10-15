# Spec Delta

## ADDED Requirements

### Requirement: Development mode is off unless the deployment environment enables it
Development mode SHALL be off by default and SHALL be controlled exclusively by a deployment environment flag, with a secure (disabled) default when the flag is absent. The production configuration file SHALL NOT carry development mode enabled, and editing the configuration file SHALL NOT be the way to enable it.

#### Scenario: Deployment without the environment flag
- **WHEN** the application runs in an environment that does not define the development flag
- **THEN** development mode is off

#### Scenario: Local environment enables development explicitly
- **WHEN** a local environment defines the development flag to enable development mode
- **THEN** development mode is on for that environment only
- **AND** other deployments without the flag remain unaffected

#### Scenario: Production configuration does not enable development
- **WHEN** the deployed configuration file is inspected in any environment
- **THEN** it does not carry development mode enabled

### Requirement: Errors do not leak internal details when development mode is off
With development mode off, responses SHALL NOT expose stack traces, internal file paths, or SQL fragments — whether from fatal errors or from warnings and notices emitted during the request. Error details SHALL be written only to the configured server error log, and clients SHALL receive the standard error envelope.

#### Scenario: Unexpected error during a request
- **WHEN** an unexpected error occurs while development mode is off
- **THEN** the client response contains the standard error envelope without stack traces, internal paths, or SQL fragments
- **AND** the full details are written to the configured error log

#### Scenario: Warning or notice emitted during a request
- **WHEN** a warning or notice is emitted while development mode is off
- **THEN** it does not appear in the response body or headers
- **AND** it is written to the configured error log
