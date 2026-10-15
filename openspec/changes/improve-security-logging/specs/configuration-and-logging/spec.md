# Spec Delta

## ADDED Requirements

### Requirement: Security-relevant events are recorded
The system SHALL record a log entry for each security-relevant event: failed authentication (invalid or missing credentials, missing, invalid or expired access token), authorization denials (insufficient role or blocked privilege escalation), rate-limit rejections, file uploads rejected by validation or policy, and invalid or unexpected request methods. Each entry SHALL carry enough context to correlate the event: the module or component where it occurred, the outcome or response code, and the acting identity (user id or e-mail) when one is known.

#### Scenario: Failed authentication is recorded
- **WHEN** a login attempt fails because credentials are invalid
- **THEN** a log entry is written identifying the event as an authentication failure, the module where it happened, and the acting identity when it is known

#### Scenario: Authorization denial is recorded
- **WHEN** a request is denied because the role is not allowed or a row is not editable by the acting role
- **THEN** a log entry is written with the module, the denial outcome, and the acting identity when it is known

#### Scenario: Rate-limit rejection is recorded
- **WHEN** a request is rejected for exceeding the rate limit
- **THEN** a log entry is written with the endpoint context and the rejection outcome

#### Scenario: Rejected upload is recorded
- **WHEN** a file upload is refused by validation or policy
- **THEN** a log entry is written identifying the module and the rejection outcome

#### Scenario: Entries can be correlated
- **WHEN** several security events are reviewed after the fact
- **THEN** each entry includes timestamp, module or component, outcome or response code, and the acting identity when it is known, so the events can be lined up in time

### Requirement: Secrets never travel in URL query strings
Passwords, API access tokens, and recovery codes SHALL NOT appear in a URL query string in clear text or in a form from which they can be recovered (for example a structured payload encoded with base64). The password-recovery flow SHALL authenticate completion of recovery with a single-use hash carried in the recovery link; that single-use hash SHALL be the only recovery credential ever present in a query string, and it SHALL be accepted only until recovery completes or the recovery window expires.

#### Scenario: Recovery link carries only the single-use hash
- **WHEN** the system builds the URL used to fetch and render the recovery template
- **THEN** the query string contains the single-use recovery hash and non-secret parameters such as the recipient role and e-mail address
- **AND** it does not contain the recovery code nor any encoded payload from which a credential can be recovered

#### Scenario: Internal fetch leaves no recoverable credential behind
- **WHEN** the backend fetches the recovery template over an internal URL
- **THEN** host or proxy access logs for that request can expose only the single-use hash and non-secret parameters, never a reusable credential

#### Scenario: A logging failure does not expose the URL secret
- **WHEN** rendering the recovery e-mail fails and the failure is recorded
- **THEN** the recorded context identifies the failure without including a URL that carries any credential

#### Scenario: Single-use hash stops working after recovery
- **WHEN** recovery completes successfully or the recovery window expires
- **THEN** the single-use hash carried by the link is no longer accepted

### Requirement: Log entries carry no secret values
Log entries SHALL identify an event without containing secret values: passwords, access tokens, recovery codes, one-time recovery hashes, session identifiers, or credential hashes SHALL not be written to log entries. The entry SHALL reference the affected resource by a non-secret identifier such as user id or e-mail instead.

#### Scenario: Recovery failure is logged without credential material
- **WHEN** storing or consuming a recovery credential fails and the failure is logged
- **THEN** the entry references the affected user or operation but contains no recovery code, hash, or credential hash

#### Scenario: Log file is searched for secrets
- **WHEN** the log files are inspected for credential material after a security event review
- **THEN** no password, token, recovery code, recovery hash, or credential hash appears in the entries

### Requirement: Logging failures do not break requests or leak paths
A failure to write a log entry SHALL NOT abort or replace the response of the request being served, and no client-visible output SHALL disclose the filesystem path or name of a log file. Internal logging problems SHALL remain visible only in operational channels, not in API responses.

#### Scenario: Log file cannot be written
- **WHEN** the log file or log directory is not writable while a request is processed
- **THEN** the request still returns its normal response with its normal status code
- **AND** the response body does not report the logging failure

#### Scenario: Client response carries no filesystem details
- **WHEN** any internal logging problem occurs during a request
- **THEN** the client receives no filesystem path, log file name, or internal logging message
