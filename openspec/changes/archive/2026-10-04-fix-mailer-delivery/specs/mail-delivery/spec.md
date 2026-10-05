# Spec Delta

## Purpose

Defines how the shared mail helper builds and sends a message from its parameters and configuration, so callers can send a plain message without supplying every optional field and credentials come from the correct source.

## ADDED Requirements

### Requirement: Optional message fields are optional
The mail helper SHALL use `cc`, `bcc`, `attachments`, and `reply_to` only when they are present and non-empty, and SHALL send a valid message when they are absent.

#### Scenario: Only required fields
- **WHEN** a message is sent with only sender, recipients, subject, and body
- **THEN** it is sent without error
- **AND** no warning or fatal is raised for the absent optional fields

#### Scenario: Optional fields provided
- **WHEN** a message includes `cc`, `bcc`, `attachments`, or `reply_to`
- **THEN** each provided field is applied to the message

### Requirement: Sender is read correctly
The mail helper SHALL read the sender address and sender name from the caller-supplied sender data, and the sent message SHALL carry that name.

#### Scenario: Sender name applied
- **WHEN** a caller supplies a sender address and name
- **THEN** the sent message shows that name as the sender

### Requirement: SMTP settings come from the configured section
The mail helper SHALL configure the transport from the configuration section that holds the SMTP host, port, security, credentials, and sender, and SHALL NOT parse that section's auth key as JSON.

#### Scenario: Transport configured from config
- **WHEN** the helper sends a message
- **THEN** it connects using the configured host, port, security, and credentials
- **AND** it does not attempt to JSON-decode a non-JSON configuration value

### Requirement: Mail is sent as UTF-8
The mail helper SHALL send the subject and body without the removed `utf8_decode()` function and SHALL declare a UTF-8 charset.

#### Scenario: Non-ASCII content
- **WHEN** a subject or body contains non-ASCII characters
- **THEN** the message is sent using UTF-8
- **AND** no call to a removed PHP function occurs

### Requirement: Failures are reported
When a message cannot be sent, the mail helper SHALL signal failure to the caller rather than returning silently.

#### Scenario: Send failure
- **WHEN** the transport or the mail server rejects the message
- **THEN** the helper signals failure with an application error
- **AND** the error identifies the sender/message context for troubleshooting
