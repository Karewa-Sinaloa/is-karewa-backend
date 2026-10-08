# api-rate-limiting Specification

## Purpose
Defines how the API counts and limits incoming requests per client and endpoint, and how it rejects requests that exceed their limit.

## Requirements

### Requirement: Requests are limited per client and endpoint
The API SHALL count requests per client IP address and per resolved endpoint, and SHALL enforce a limit over a configurable time window.

#### Scenario: Within the limit
- **WHEN** a client stays within its limit for an endpoint
- **THEN** the request proceeds normally

#### Scenario: Exceeding the limit
- **WHEN** a client exceeds its limit for an endpoint
- **THEN** the request is rejected without executing the endpoint behavior

### Requirement: Sensitive endpoints have stricter limits
Endpoints that accept credentials or trigger side effects (such as login and account recovery) SHALL support a stricter limit than general read endpoints.

#### Scenario: Repeated login attempts
- **WHEN** a client repeatedly calls a credential-accepting endpoint beyond its stricter limit
- **THEN** further attempts within the window are rejected

### Requirement: Over-limit responses are explicit
A rejected request SHALL return the dedicated rate-limit response code with HTTP status `429` and SHALL include a `Retry-After` header indicating when the client may retry.

#### Scenario: Rate-limited response
- **WHEN** a request is rejected for exceeding its limit
- **THEN** the response uses the rate-limit code and HTTP status `429`
- **AND** it includes a `Retry-After` header

### Requirement: Client identity is resolved behind the tunnel proxy
The API SHALL be served through a Cloudflare tunnel (`cloudflared`), so the connection address is the tunnel's private address rather than the end user. When the peer is a trusted proxy, the API SHALL derive the client identity from the proxy-provided client-IP header (`CF-Connecting-IP`) and SHALL key limits by that client identity rather than by the tunnel address. The set of trusted peers SHALL be configurable and SHALL contain only addresses that cannot be reached directly by untrusted clients.

#### Scenario: Requests behind the tunnel
- **WHEN** a request arrives from a trusted proxy peer carrying the client-IP header
- **THEN** the limit is keyed by the forwarded client address
- **AND** it is not keyed by the tunnel/proxy address

#### Scenario: Two users behind the same tunnel
- **WHEN** two different forwarded client addresses reach the API through the same tunnel peer
- **THEN** their requests are counted against separate limits

#### Scenario: Proxy header is not trusted from arbitrary peers
- **WHEN** a request arrives from a peer that is not trusted and carries a client-IP header
- **THEN** the header is ignored and the connection address is used

### Requirement: Limiting is configurable and reliable
The limits and window SHALL be configurable. Counters SHALL be stored server-side and SHALL not allow an unauthenticated client to bypass the limit by changing client-controlled values.

#### Scenario: Configuration change
- **WHEN** an operator changes a limit or window in configuration
- **THEN** the API enforces the new value

#### Scenario: Counter storage
- **WHEN** identical requests arrive from the same client
- **THEN** they share one counter regardless of other client-supplied values

#### Scenario: Spoofing a client identity
- **WHEN** a client not permitted to present a client-IP header sends one
- **THEN** the header does not change which counter its requests use
