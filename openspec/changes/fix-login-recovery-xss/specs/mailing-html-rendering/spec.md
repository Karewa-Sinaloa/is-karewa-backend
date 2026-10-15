# Spec Delta

## Purpose

Garantiza que las respuestas HTML producidas por el módulo `mailings` escapan por defecto todo valor controlado por el usuario y validan los parámetros del enlace que renderizan, de modo que el XSS reflejado sea imposible.

## ADDED Requirements

### Requirement: Rendered HTML escapes user-controlled values
The mailings module SHALL escape every user-controlled value when producing an HTML response, including values placed inside attributes such as `href`. A user-controlled value SHALL NOT be able to close the attribute or tag that contains it.

#### Scenario: Recovery link attribute holds an escaped value
- **WHEN** the recovery page is rendered for a well-formed `recovery_hash`
- **THEN** the value appears in the `href` attribute in escaped (entity- or percent-encoded) form
- **AND** the link still points to the intended reset URL

#### Scenario: Value cannot break out of its attribute
- **WHEN** the recovery page is rendered with a `recovery_hash` containing a double quote and further markup
- **THEN** the rendered HTML keeps the value inside the `href` attribute
- **AND** no additional attribute or event handler is created in the document

### Requirement: Recovery link parameters are validated against their expected format
The mailings module SHALL validate each parameter used to build the recovery page against its strict expected format (the issued recovery hash, the six-digit recovery code, the user role) and SHALL reject the request with an error response when a parameter does not match. Rejected requests SHALL NOT render the recovery page.

#### Scenario: Malformed recovery hash is rejected
- **WHEN** a request carries a `recovery_hash` outside the expected format (for example containing quotes, spaces, or angle brackets)
- **THEN** the module responds with an error instead of the HTML page
- **AND** no recovery link is rendered

#### Scenario: Well-formed parameters render the page
- **WHEN** a request carries a well-formed `recovery_hash`, `code`, and `role`
- **THEN** the module renders the recovery page with a usable reset link

### Requirement: Crafted query values never reach the HTML as executable markup
A query value crafted to inject markup SHALL NOT appear in the served HTML as executable markup, whether the request is rejected or the value is rendered escaped.

#### Scenario: Quote plus event handler payload
- **WHEN** a request carries `recovery_hash` set to a quote followed by an event handler (for example `" onclick=alert(1) x="`)
- **THEN** the response contains no event-handler attribute derived from that value
- **AND** no script executes when the response is opened in a browser

#### Scenario: Script tag payload
- **WHEN** a request carries a `recovery_hash` containing `<script>` markup
- **THEN** the served HTML contains no executable script element originating from that value
