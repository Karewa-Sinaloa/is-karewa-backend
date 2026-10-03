# Architecture

## Purpose

Describes the runtime architecture of the Monitor Karewa backend: the technology choices, the request lifecycle, and the conventions the in-house micro-framework follows. Agents working in this repository MUST treat this as the canonical description of how the system is wired.

## Requirements

### Requirement: Backend technology stack
The backend SHALL be a custom PHP REST API built with a vanilla, in-house micro-framework. It SHALL NOT be Laravel, Symfony, or any other full-stack framework, and agents SHALL NOT introduce a full-stack framework.

The runtime SHALL target PHP 8.4+ and MySQL 8.0+ / MariaDB 11.4 for local Docker development. Apache with `.htaccess` rewrites, or Nginx with equivalent rewrites, SHALL serve the API. Composer dependencies SHALL be managed from the repository root.

#### Scenario: Choosing an implementation approach
- **WHEN** an agent implements a feature
- **THEN** it uses the existing in-house micro-framework and its conventions
- **AND** it does not add a full-stack framework or replace the ORM

### Requirement: Key dependencies
The project SHALL rely on the following Composer packages: `firebase/php-jwt`, `phpmailer/phpmailer`, `symfony/yaml`, `gumlet/php-image-resize`, `curl/curl`, `ramsey/uuid`, `smarty/smarty`, and `phpunit/phpunit`.

#### Scenario: Adding a dependency
- **WHEN** an agent needs a capability already provided by a key package
- **THEN** it reuses that package instead of adding a duplicate dependency

### Requirement: Public entry point
The public entry point SHALL be `httpdocs/api.php`, which bootstraps the core application in `app/core/bootstrap/init.php`.

#### Scenario: Trace the start of a request
- **WHEN** an agent needs to understand how a request begins
- **THEN** it starts from `httpdocs/api.php` and follows into `app/core/bootstrap/init.php`

### Requirement: Request flow
A request SHALL follow this flow: HTTP request → rewrite rules → `httpdocs/api.php` → `app/core/bootstrap/init.php` → `app/core/bootstrap/midelware.php` → `app/core/bootstrap/modules.php` → `app/core/bootstrap/routes.php` → module `index.php` → `controller.php`.

#### Scenario: Locating behavior in a request
- **WHEN** an agent investigates where a behavior happens
- **THEN** it follows this flow in order before editing code

### Requirement: Routing
Apache rewrites SHALL map friendly URLs to query parameters in the form `?m=<module>` plus optional `id` and `s`.

| URL | Query |
| --- | --- |
| `/api/v5/materias` | `?m=materias` |
| `/api/v5/materias/42` | `?m=materias&id=42` |
| `/api/v5/materias/42/attachments` | `?m=materias&id=42&s=attachments` |

#### Scenario: Resolving a friendly URL
- **WHEN** a client calls a friendly URL
- **THEN** the rewrite rules translate it to `m`/`id`/`s` query parameters

### Requirement: Module resolution
Modules SHALL be resolved from the map in `app/core/bootstrap/routes.php`. The runtime search order SHALL be `app/api/` first, then `app/core/modules/`. Module names SHALL use kebab-case.

#### Scenario: Resolving a module name
- **WHEN** a request references a module
- **THEN** the runtime looks in `app/api/` first and falls back to `app/core/modules/`
- **AND** the module directory name is kebab-case

### Requirement: Repository layout
The repository SHALL be organized as follows:

- `httpdocs/` - public web root
- `httpdocs/api.php` - API entry point
- `httpdocs/.htaccess` - Apache routing rules
- `app/api/` - application modules
- `app/core/` - framework core
- `app/core/bootstrap/` - request bootstrap, routing, and module loading
- `app/core/model/` - data access layer and base model behavior
- `app/core/auth/` - JWT/session/authentication helpers
- `app/core/helpers/` - shared helpers, API response, logging, utilities
- `app/core/validation/` - input validation
- `app/core/modules/` - shared/core modules
- `app/core/components/` - reusable components
- `app/core/config/` - runtime constants and API codes
- `app/.keys/` - JWT keys and other gitignored secrets
- `tests/` - PHPUnit tests
- `resources/` - shared SQL, fixtures, and imported artifacts
- `docker/` - container definitions
- `openspec/` - spec-driven workflow artifacts
- `logs/` - runtime logs

#### Scenario: Placing a new file
- **WHEN** an agent creates a file
- **THEN** it places it in the directory that matches this layout

### Requirement: HTTP method mapping
The framework SHALL map HTTP requests to module methods as follows:

| HTTP | `?id` present | Request type | Method |
| --- | --- | --- | --- |
| GET | No | `index` | `index()` |
| GET | Yes | `show` | `show()` |
| POST | No | `store` | `store()` |
| PUT | Yes | `update` | `update()` |
| DELETE | Yes | `destroy` | `destroy()` |

#### Scenario: Determining the target method
- **WHEN** a request arrives
- **THEN** the HTTP verb and the presence of `?id` determine the request type and target method
