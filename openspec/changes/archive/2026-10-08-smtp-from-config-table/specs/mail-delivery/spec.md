# Spec Delta

## RENAMED Requirements

- FROM: `### Requirement: SMTP settings come from the configured section`
- TO: `### Requirement: SMTP settings come from the smtp_config row`

## MODIFIED Requirements

### Requirement: SMTP settings come from the smtp_config row
The mail helper SHALL configure the transport from the `config` table row whose `slug` is `smtp_config`, decoding its `value` as JSON and mapping `host`, `port`, `security`, `user`, `pass` and `from` to host, port, security, username, password and sender address. Because the row carries no `smtp_auth`, `debug` or `from_name`, the helper SHALL derive `smtp_auth` from `user` and `pass`, run with debugging off, and use no sender name.

#### Scenario: Transport configured from config
- **WHEN** the helper sends a message while a valid `smtp_config` row is present
- **THEN** it connects using the host, port, security, user and password from that row
- **AND** the message is sent from the address stored in the row's `from`

#### Scenario: Stored credentials enable authentication
- **WHEN** the row stores a non-empty `user` and a non-empty `pass`
- **THEN** the transport attempts SMTP authentication with those values

#### Scenario: Missing stored credentials disable authentication
- **WHEN** the row stores an empty `user` or an empty `pass`
- **THEN** the transport connects without attempting authentication

#### Scenario: Row-only defaults for debugging and sender name
- **WHEN** the helper sends a message while the row is in effect and no mail environment variable is set
- **THEN** transport debugging is off
- **AND** the message carries no configured sender name

## ADDED Requirements

### Requirement: A missing or malformed SMTP row does not block sending
When the `config` table has no row with `slug = smtp_config`, or that row's `value` is not valid JSON, the mail helper SHALL fall back to the `mailing` settings from `app/config.yml` and SHALL record the problem with `error_logs()` instead of failing the send.

#### Scenario: Row is absent
- **WHEN** a message is sent and no `config` row has the `smtp_config` slug
- **THEN** the transport is configured from `app/config.yml`
- **AND** the missing row is recorded with `error_logs()`
- **AND** the send is still attempted

#### Scenario: Value is not valid JSON
- **WHEN** the row exists but its `value` cannot be decoded as JSON
- **THEN** the transport is configured from `app/config.yml`
- **AND** the malformed value is recorded with `error_logs()`
- **AND** the send is still attempted

### Requirement: Stored SMTP settings are re-read for every message
The mail helper SHALL resolve the SMTP settings when it sends a message, so that an update to the `smtp_config` row through the config API takes effect on the next message without restarting the application.

#### Scenario: Row updated through the API
- **WHEN** an authorized client updates the `smtp_config` row and a message is sent afterwards
- **THEN** the message uses the host and credentials stored in the updated row
