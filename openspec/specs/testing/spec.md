# Testing

## Purpose

Describes how the Monitor Karewa backend is tested and how to run tests. Agents MUST verify changes with the test suite.

## Requirements

### Requirement: PHPUnit configuration
Tests SHALL be run with PHPUnit, configured from `phpunit.xml` in the repository root. Tests SHALL live in `tests/`.

#### Scenario: Adding a test
- **WHEN** an agent writes a test
- **THEN** it places it under `tests/` and runs it with PHPUnit

### Requirement: Running tests
The full suite SHALL be run with `./vendor/bin/phpunit`. A single test file SHALL be run with `./vendor/bin/phpunit tests/ValidationTest.php`.

#### Scenario: Verifying a change
- **WHEN** an agent completes an implementation task
- **THEN** it runs the relevant tests and, before finishing, the full suite

### Requirement: Test environment
The test bootstrap SHALL define the constants tests need (for example `ROOT_PATH`, `CORE_PATH`, `MODULE`, `JWT_ENCODING`, `JWTKEYS_PATH`, `SESSION_TIME`) and SHALL follow the existing pattern of loading application files explicitly and stubbing global helpers such as `error_logs()`.

#### Scenario: Writing a model test
- **WHEN** a test needs application classes
- **THEN** it requires the concrete files explicitly rather than relying on PSR-4 autoloading
