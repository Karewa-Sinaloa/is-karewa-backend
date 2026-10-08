## Purpose

La API necesita una referencia pública, estable y versionada para que terceros puedan integrar sus clientes sin depender de autenticación ni de inspección del código fuente.

## ADDED Requirements

### Requirement: Public OpenAPI document

The system MUST provide a public OpenAPI JSON document at `api/docs/openapi.json`.

#### Scenario: Document is reachable without authentication

- **WHEN** a visitor requests `api/docs/openapi.json`
- **THEN** the system returns the OpenAPI document without requiring credentials

#### Scenario: Document is versioned

- **WHEN** the document is published
- **THEN** it includes explicit API version metadata in the OpenAPI `info` block

### Requirement: Public Scalar documentation UI

The system MUST expose a public Scalar-based documentation UI without authentication.

#### Scenario: Anonymous visitor opens docs UI

- **WHEN** an anonymous visitor accesses the documentation interface
- **THEN** they can browse the API documentation without signing in

#### Scenario: UI consumes the public JSON spec

- **WHEN** the documentation UI loads
- **THEN** it reads the OpenAPI definition from the public JSON document

### Requirement: Document protected and public endpoints

The system MUST include both public and protected endpoints in the OpenAPI document until a later scope change removes protected coverage.

#### Scenario: Protected endpoint is listed

- **WHEN** the OpenAPI document is generated or maintained
- **THEN** protected endpoints are present in the published contract

#### Scenario: Security scheme is described

- **WHEN** a protected endpoint is documented
- **THEN** the spec includes the required security scheme and usage expectations

### Requirement: Future GitHub Pages compatibility

The documentation structure MUST remain compatible with future hosting from GitHub Pages as a static site.

#### Scenario: Static hosting can reuse the spec artifact

- **WHEN** the docs are later published from a static host
- **THEN** the existing JSON artifact can be served without redesigning the contract

#### Scenario: No server-only coupling is introduced

- **WHEN** the documentation is reviewed for portability
- **THEN** it does not depend on a runtime-only generation flow for its published contract
