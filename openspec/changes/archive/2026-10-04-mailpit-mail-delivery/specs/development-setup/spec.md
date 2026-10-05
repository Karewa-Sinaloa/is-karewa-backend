# Spec Delta

## ADDED Requirements

### Requirement: Local mail is captured by Mailpit
The local Docker stack SHALL route messages sent by the application through the Mailpit service using the existing `mailing` configuration, so local messages are captured for inspection instead of being delivered to a real provider.

#### Scenario: Local message is captured
- **WHEN** the local stack is running and the application sends a message
- **THEN** Mailpit captures the message
- **AND** the message is visible in the Mailpit interface

#### Scenario: Example environment selects Mailpit
- **WHEN** a developer copies the tracked environment example to the local environment file and starts the stack
- **THEN** the application's mail target points to the Mailpit service
- **AND** no change to `app/config.yml` is required

#### Scenario: Documentation points to the local inbox
- **WHEN** a developer needs to confirm local mail delivery
- **THEN** the development documentation states the Mailpit interface address and the mail variables used to select it
