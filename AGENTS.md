# AGENTS.md - Monitor Karewa Backend

## Purpose

This is the canonical operating guide for agents working in this repository.
If another document conflicts with this file, this file wins.

This file is intentionally short. The technical knowledge that used to live here now lives as OpenSpec specifications under `openspec/specs/`. **Before starting a task, read the specifications relevant to it.** Do not edit the knowledge here; edit the spec and keep this file as a pointer.

## Read The Specs First

OpenSpec is the source of truth for how this project works. Specs are in `openspec/specs/<capability>/spec.md`. Read the ones that match your task:

| Capability | Read when you need |
| --- | --- |
| `openspec/specs/architecture/spec.md` | Tech stack, request flow, routing, repository layout, HTTP method mapping |
| `openspec/specs/module-conventions/spec.md` | Module structure, `index.php` pattern, `$moduleFields`, module state |
| `openspec/specs/authentication-and-authorization/spec.md` | JWT, roles, `ModuleHandler::Validate()`, hash auth |
| `openspec/specs/validation/spec.md` | `$rules`, supported rules, `FieldsValidator` |
| `openspec/specs/query-conventions/spec.md` | Pagination, sorting, filtering, search |
| `openspec/specs/api-responses/spec.md` | `ApiResponse::Set()`, response codes, envelope |
| `openspec/specs/configuration-and-logging/spec.md` | Config loading, globals, logging |
| `openspec/specs/testing/spec.md` | PHPUnit setup and how to run tests |
| `openspec/specs/development-setup/spec.md` | Docker stack and quick commands |

Related documents (Read only if needed):

- `README.md`, `DEV_ENV_MANUAL.md`, `api_response.md`, `orm_wiki.md`
- `SECURITY.md` - CORS allow-list, rate limiting, and security headers
- `app/core/config/api_codes.yml`
- `.github/copilot-instructions.md`

## Process Rules

- This file wins over any other document when there is a conflict.
- OpenSpec is used for specification work. Keep specs in `openspec/`; do not duplicate spec content here.
- Before a task, read the specs listed above that apply to it.
- When a process rule is required, it goes here; everything else belongs in a spec.
- If any other document still mentions old paths like `app/core/third_party` or `../../test`, treat that content as outdated and ignore it in favor of this file.

## Spec-Driven Workflow

- `openspec/specs/` contains current specifications.
- `openspec/changes/` contains active change work.
- `openspec/changes/archive/` contains archived changes.
- Keep specs and change artifacts out of this file unless a process rule must be stated here.

## Quick Commands

Run commands from the repository root. See `openspec/specs/development-setup/spec.md` for the full list and `openspec/specs/testing/spec.md` for testing.

```bash
composer install
./vendor/bin/phpunit
make up
make down
make test
```

## Key References

- `openspec/specs/` - the canonical technical specifications
- `README.md`
- `DEV_ENV_MANUAL.md`
- `api_response.md`
- `orm_wiki.md`
- `app/core/config/api_codes.yml`
- `.github/copilot-instructions.md`
