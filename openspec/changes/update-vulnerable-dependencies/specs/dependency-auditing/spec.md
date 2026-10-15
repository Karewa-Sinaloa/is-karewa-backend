# Spec Delta

## Purpose

Installed PHP dependencies are kept free of known security advisories through an automated audit gate that runs as part of the project's verification routine, with fixes released under a defined patch policy.

## ADDED Requirements

### Requirement: Installed dependencies report zero known advisories
Auditing the installed PHP dependencies against the committed lock file SHALL report zero known security advisories. Packages affected by a published advisory SHALL be updated to a non-affected version before the audit gate is considered passing.

#### Scenario: Audit on the committed lock file
- **WHEN** the dependency security audit is executed for the repository as committed
- **THEN** it reports no known security advisories affecting installed packages

#### Scenario: An advisory affects an installed package
- **WHEN** a published security advisory affects a version of a package present in the lock file
- **THEN** the audit reports that package and advisory, and the gate does not pass until the package is updated past the affected range

### Requirement: Advisory fixes do not break the application
A dependency update applied to fix a security advisory SHALL leave the application's observable behavior unchanged: the full test suite SHALL pass and the API contract SHALL be unaffected.

#### Scenario: Applying an update that fixes an advisory
- **WHEN** a package is updated to a version that fixes a security advisory
- **THEN** the full test suite passes without regressions
- **AND** the API responses of the affected functionality remain unchanged

### Requirement: The audit runs in the verification pipeline
The dependency security audit SHALL be runnable as a single documented project command, and SHALL fail loudly (non-zero exit) when advisories are found. It SHALL be executed as part of the project's verification routine, alongside the test suite.

#### Scenario: Running the project audit command
- **WHEN** an agent runs the documented dependency audit command
- **THEN** it executes the composer security audit for the installed dependencies
- **AND** it exits with a non-zero status when any advisory is found

#### Scenario: Verification before finishing a change
- **WHEN** a change to dependencies or the lock file is completed
- **THEN** the audit command and the full test suite are both run before the change is considered verified
