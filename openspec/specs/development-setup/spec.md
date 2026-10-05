# Development Setup

## Purpose

Describes the local development environment and the commands agents use day to day. Agents MUST use these commands from the repository root.

## Requirements

### Requirement: Docker stack
The local stack SHALL be defined in `docker-compose.yml`, with the main services `php`, `nginx`, `db`, `mailpit`, and an optional `cloudflared`. The PHP container working directory SHALL be `/var/www/html`.

#### Scenario: Starting local development
- **WHEN** an agent needs the local environment
- **THEN** it uses the commands defined in the `Makefile` and the details in `DEV_ENV_MANUAL.md`

### Requirement: Quick commands
The following commands SHALL be available and run from the repository root:

```bash
composer install
./vendor/bin/phpunit
./vendor/bin/phpunit tests/ValidationTest.php
make up
make down
make logs
make ps
make shell-php
make composer-install
make test
make tunnel-up
make tunnel-down
make tunnel-logs
```

#### Scenario: Running the stack
- **WHEN** an agent starts, stops, or inspects the stack
- **THEN** it uses the corresponding `make` target

#### Scenario: Docker details
- **WHEN** an agent needs Docker environment details
- **THEN** it reads `DEV_ENV_MANUAL.md`

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
