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
