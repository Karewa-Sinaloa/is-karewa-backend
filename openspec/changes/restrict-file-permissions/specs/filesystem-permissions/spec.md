# Spec Delta

## Purpose

Sensitive files and directories are created and kept with least-privilege filesystem permissions, so that secrets and runtime logs are accessible only to the account that runs the application.

## ADDED Requirements

### Requirement: JWT signing private key is readable only by its owner
The JWT signing private key SHALL have file mode 0600 and the directory that contains it SHALL have mode 0700. The invariant SHALL hold after a fresh installation and after a fresh checkout of the repository.

#### Scenario: Inspecting the key in the worktree
- **WHEN** the private key path and its directory are inspected with filesystem tools
- **THEN** the key has mode 0600 and the directory has mode 0700

#### Scenario: Re-running the installer on a widened key
- **WHEN** the installer runs on a worktree whose key permissions were widened (for example by a fresh checkout)
- **THEN** the key is restored to mode 0600 and its directory to mode 0700 without regenerating the key pair

### Requirement: Log paths use restricted permissions
The runtime log directory SHALL have mode 0750 and log files SHALL have mode 0640. Log paths SHALL NOT be created or widened to mode 0777. A permission correction SHALL take effect on the filesystem path it targets.

#### Scenario: First write creates the log path
- **WHEN** the application writes its first log entry while the log directory or the log file does not exist yet
- **THEN** the directory ends up with mode 0750 and the file with mode 0640

#### Scenario: Correcting an existing log path
- **WHEN** an existing log directory or log file has broader permissions than required
- **THEN** the correction changes the mode of that path to 0750 or 0640 respectively and completes without error

### Requirement: Upload directories are not world-writable
Upload directories SHALL be created with mode 0755 and SHALL NOT be world-writable.

#### Scenario: A new upload folder is created
- **WHEN** the application creates a new folder to store an uploaded image or attachment
- **THEN** the folder has mode 0755 and is not writable by other users of the system

#### Scenario: An existing upload folder is world-writable
- **WHEN** an upload directory exists with mode 0777
- **THEN** the permission maintenance step restores it to mode 0755

### Requirement: Filesystem permission drift is detected
The project SHALL provide a documented verification command that compares the modes of the sensitive paths — private key, key directory, log directory, log files and upload directories — against the required values, reports each deviation with the offending path, the found mode and the expected mode, and exits with a non-zero status when any deviation exists.

#### Scenario: A sensitive path drifted
- **WHEN** the private key has mode 0644 after a fresh checkout and the verification command runs
- **THEN** the command reports that path with the found mode 0644 and the expected mode 0600, and exits with a non-zero status

#### Scenario: All sensitive paths comply
- **WHEN** every sensitive path has its required mode
- **THEN** the verification command reports success and exits with status 0
