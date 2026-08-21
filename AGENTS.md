# AGENTS.md - Monitor Karewa Backend

## Purpose

This file is the canonical operating guide for agents working in this repository.
If another document conflicts with this file, this file wins.

OpenSpec is used for specification work. Keep specs in `openspec/` and do not duplicate them here unless a process rule is required.

## Project Overview

Monitor Karewa Backend is a custom PHP REST API built with a vanilla, in-house micro-framework.
It is not Laravel, Symfony, or any other full-stack framework.

The public entry point is `httpdocs/api.php`, which bootstraps the core application in `app/core/bootstrap/init.php`.

## Tech Stack

- PHP 8.4+
- MySQL 8.0+ / MariaDB 11.4 for local Docker development
- Apache with `.htaccess` rewrites or Nginx with equivalent rewrites
- Composer dependencies managed from the repository root
- Key packages: `firebase/php-jwt`, `phpmailer/phpmailer`, `symfony/yaml`, `gumlet/php-image-resize`, `curl/curl`, `ramsey/uuid`, `smarty/smarty`, `phpunit/phpunit`

## Repository Layout

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

## Quick Commands

Run commands from the repository root unless noted otherwise.

```bash
composer install
./vendor/bin/phpunit
./vendor/bin/phpunit tests/ValidationTest.php
make up
make down
make logs
make ps
make shell-php
make composer-install
make test
make tunnel-up
make tunnel-down
make tunnel-logs
```

For Docker environment details, use `DEV_ENV_MANUAL.md`.

## Architecture

### Request Flow

`HTTP request -> rewrite rules -> httpdocs/api.php -> app/core/bootstrap/init.php -> app/core/bootstrap/midelware.php -> app/core/bootstrap/modules.php -> app/core/bootstrap/routes.php -> module index.php -> controller.php`

### Routing

Apache rewrites map friendly URLs to query params.

| URL | Query |
| --- | --- |
| `/api/v5/materias` | `?m=materias` |
| `/api/v5/materias/42` | `?m=materias&id=42` |
| `/api/v5/materias/42/attachments` | `?m=materias&id=42&s=attachments` |

### Module Resolution

Modules are resolved from `app/core/bootstrap/routes.php`.
The runtime search order is `app/api/` first, then `app/core/modules/`.

Module names use kebab-case.

## Module Structure

Each module normally contains two files:

- `index.php` - declares accepted methods and authorization rules, then calls `ModuleHandler::Validate()`
- `controller.php` - defines the module class and extends `BaseModel`

Example pattern:

```php
require_once __DIR__ . '/controller.php';
$module = new ExampleComponent();

$accepted_methods = [
  'index'   => [false],
  'show'    => [false],
  'store'   => [true, [1, 2, 3]],
  'update'  => [true, [1, 2, 3]],
  'destroy' => [true, [1, 2, 3]],
];

ModuleHandler::Validate($accepted_methods, $module);
```

`BaseModel` provides the `Crud` trait and common query/validation behavior.

## HTTP Method Mapping

| HTTP | `?id` present | Request type | Method |
| --- | --- | --- | --- |
| GET | No | `index` | `index()` |
| GET | Yes | `show` | `show()` |
| POST | No | `store` | `store()` |
| PUT | Yes | `update` | `update()` |
| DELETE | Yes | `destroy` | `destroy()` |

## Module Fields

`$moduleFields` supports these keys:

| Key | Default | Meaning |
| --- | --- | --- |
| `field` | `null` | Database column or expression |
| `filter` | `true` | Usable as a query filter |
| `saved` | `true` | Written on create/update |
| `listed` | `true` | Returned in GET responses |
| `default` | `null` | Fallback value |
| `optional` | `false` | Skip on update when missing |
| `roles` | `false` | Role IDs allowed to access the field |

Common module state:

- `$get_params` with `table`, `filters`, `joins`, `search`, and optional `group`
- `$rules` for validation
- `$table_assoc` for cascading delete relationships
- `$methodOptions['end'] = false` to return raw data instead of terminating with `ApiResponse::Set()`

## Authentication And Authorization

- JWT uses RS256
- Tokens are sent in the `Authorization` header
- Keys live in `app/.keys/`
- `ModuleHandler::Validate()` checks authentication and role access
- `AUTHENTICATED` and `USER_ROLE` are defined after validation
- Roles are integer IDs

## Validation

Validation rules are stored as pipe-separated strings in `$rules`.

Supported rule examples include:

`required`, `max`, `email`, `unique:table:column`, `exist:table:column`, `numeric`, `alpha`, `alpha_dash`, `alpha_spaces`, `base64`, `date_format`, `time_format`, `decimal`, `rfc`, `url`, `boolean`, `json`

`FieldsValidator::Validation($fields, $rules, $id)` returns `array|false`.
Use `$id` on updates so `unique` can ignore the current record.

## Query Conventions

- `?embed=pagination` enables pagination metadata
- `?page=N` selects the page
- `?limit=N` sets the page size
- `?sort=+field,-field` controls ordering
- `?groupby=field1,field2` groups results
- `?fields=field1,field2` limits the returned fields
- Filters use `?field=op:value`
- Supported operators: `eq`, `lt`, `gt`, `gte`, `lte`, `ne`, `lk`, `isn`, `non`, `in`
- Search normalization strips Spanish accents and common stopwords before building filters

## API Responses

All responses go through `ApiResponse::Set()`.
It writes the response payload and terminates execution with `die()`.

- Response codes live in `app/core/config/api_codes.yml`
- Every response includes `meta.session_id`
- Use `app/core/helpers/api_response.php` and `api_response.md` as references

## Configuration

- `app/config.yml` is gitignored
- Use `app/config.yml.secret` and git-secret for encrypted config handling
- Runtime constants are loaded in `app/core/config/base.php`
- Global runtime values include `$_config`, `$_payload`, and `$_apiConfig`
- Main config sections include `database`, `jwt`, `session`, `log`, `cors`, `statics`, `mailings`, `facebook`, `hcaptcha`, `uploads`, `valid_requests`, and related settings

## Logging

- Use `error_logs([$context, $code, $message, __LINE__, __FILE__])`
- Log file locations are configured through `app/config.yml`
- Runtime logs live under `logs/`

## Development Setup

- Docker stack is defined in `docker-compose.yml`
- Main services: `php`, `nginx`, `db`, `mailpit`, optional `cloudflared`
- The PHP container working directory is `/var/www/html`
- Use `DEV_ENV_MANUAL.md` for environment setup and daily workflow

## Testing

- PHPUnit is configured from `phpunit.xml` in the repository root
- Tests live in `tests/`
- Run the full suite with `./vendor/bin/phpunit`
- Run one test file with `./vendor/bin/phpunit tests/ValidationTest.php`

## Spec-Driven Workflow

OpenSpec lives in `openspec/`.

- `openspec/specs/` contains current specs
- `openspec/changes/` contains active change work
- `openspec/changes/archive/` contains archived changes
- Keep specs and change artifacts out of this file unless a process rule must be stated here

## Key References

- `README.md`
- `DEV_ENV_MANUAL.md`
- `api_response.md`
- `orm_wiki.md`
- `app/core/config/api_codes.yml`
- `.github/copilot-instructions.md`

## Final Rule

If any other document still mentions old paths like `app/core/third_party` or `../../test`, treat that content as outdated.
Update the document or ignore it in favor of this file.
