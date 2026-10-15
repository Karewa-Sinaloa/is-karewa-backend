# Proposal

## Why

La auditoría OWASP 2026-10-14 confirma el hallazgo **F-001 (Crítico)**: el método `update` del módulo `users` no aplica ninguna guarda anti-escalada, de modo que cualquier usuario autenticado puede enviarse un `PUT` con `{"role_id": 1}` y promoverse a administrador. `store()` ya protege esta vía (líneas 92-98 del controlador), pero `update()` no, y el campo `role_id` se declara sin restricción de roles ni filtro de mass-assignment.

## What Changes

- `update()` aplica las mismas guarda anti-escalada que `store()`: un caller nunca puede otorgarse un rol mejor (número menor) que el suyo.
- El campo `role_id` en `$moduleFields` declara la lista de roles autorizados a modificarlo y queda excluido del mass-assignment para callers no autorizados.
- `status_id` (y demás campos sensibles del módulo) queda sujeto a la misma disciplina de escritura restringida.
- **BREAKING**: un payload de `update` que contenga `role_id`/`status_id` dejará de tener efecto para callers sin privilegio (antes se persistía; ahora se ignora o se corrige según la guarda).

## Capabilities

### New Capabilities

- _(ninguna)_

### Modified Capabilities

- `user-module-fields`: se añaden requisitos de protección de escritura de campos privilegiados (`role_id`, `status_id`) en `store`/`update`, además de los requisitos existentes de mapeo de columnas.

## Impact

- Código: `app/core/modules/users/controller.php` (`update()`, `$moduleFields`, `$rules`), `app/core/modules/users/index.php` (declaración de métodos), posiblemente `app/core/bootstrap/midelware.php` (`queryFields`/`availableFields`).
- API: respuestas de `PUT /api/v5/users/{id}` cuando el payload incluya campos privilegiados por debajo del rol del caller.
- Tests: `tests/UsersFieldMappingTest.php` (extender), `tests/ModuleHandlerAllowedRolesTest.php` como referencia del patrón de guarda.
