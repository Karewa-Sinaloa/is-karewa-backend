# Spec Delta

## Purpose

Enforces a single active session per user by binding each JWT to a tracked session record, rejecting and blacklisting tokens that are no longer the active one, and cancelling the session when suspicious token reuse is detected.

## ADDED Requirements

### Requirement: Single active session per user
A user SHALL have at most one active session at a time. A successful login SHALL create that user's active session and invalidate any previous session.

#### Scenario: Login replaces the previous session
- **WHEN** a user who already has an active session logs in again
- **THEN** the new login becomes the only active session
- **AND** tokens issued before the new login are no longer accepted

#### Scenario: Concurrent token from another device
- **WHEN** a request presents a previously issued token that is no longer the user's active session
- **THEN** the request is rejected with a revoked/superseded session error

### Requirement: Session-bound token validation
Every authenticated request SHALL validate the presented JWT against the user's active session and the token blacklist, in addition to validating the token signature and expiry.

#### Scenario: Active token accepted
- **WHEN** an authenticated request presents the token that matches the user's active session and is not blacklisted
- **THEN** access is granted

#### Scenario: Blacklisted token rejected
- **WHEN** an authenticated request presents a token that is on the blacklist
- **THEN** access is denied regardless of the token signature and expiry

### Requirement: Suspicious token handling
When a non-active, revoked, or blacklisted token is presented, the system SHALL reject the request, cancel the user's active session, and add the presented token to the blacklist.

#### Scenario: Replayed superseded token cancels the session
- **WHEN** a token that is not the user's active session is used
- **THEN** the active session for that user is cancelled
- **AND** the presented token is added to the blacklist
- **AND** the user must log in again to obtain a valid token

#### Scenario: Cancelled session affects the active device
- **WHEN** a suspicious token triggers session cancellation
- **THEN** the user's current active token stops being accepted

### Requirement: Logout revokes the current token
Logout SHALL cancel the user's active session and add the presented token to the blacklist.

#### Scenario: Logout invalidates the token
- **WHEN** a user logs out with a valid active token
- **THEN** the active session is cancelled
- **AND** the token is added to the blacklist

#### Scenario: Reusing a token after logout
- **WHEN** a request presents a token that was used to log out
- **THEN** the request is rejected with a revoked session error

### Requirement: Persistent blacklist
Blacklist entries SHALL persist across requests and application restarts and SHALL remain effective until the revoked token's expiry.

#### Scenario: Blacklist survives a restart
- **WHEN** the application restarts after a token has been blacklisted
- **THEN** that token is still rejected

#### Scenario: Expired token needs no revocation
- **WHEN** a blacklisted token has passed its expiry
- **THEN** the entry may be pruned without affecting active sessions
