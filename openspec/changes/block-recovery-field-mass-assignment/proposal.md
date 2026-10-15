# Proposal

## Why

El hallazgo F-014 de la auditoría (`docs/auditoria-seguridad/auditoria-owasp-2026-10-14.md`): en `app/core/modules/users/controller.php` las definiciones de `recovery_code` (línea 28) y `recovery_date` (línea 29) de `$moduleFields` no incluyen `'saved' => false`, mientras que otras columnas protegidas sí lo hacen (p. ej. `hcaptcha_bypass`). El `queryFields` de `app/core/bootstrap/midelware.php` solo descarta un campo cuando `saved` es falso (línea 458; el defecto es `saved => true`, línea 485), de modo que un cliente autenticado puede enviar esos dos campos en `store`/`update` del módulo `users` y persistirlos. Un atacante puede así escribir en su propia fila un `recovery_code` que sea el hash bcrypt de un código que él elige y un `recovery_date` reciente, y a continuación completar `access/reset` con ese código **sin recibir el correo**, eludiendo el factor de recuperación y tomando la cuenta.

## What Changes

- `recovery_code` y `recovery_date` dejan de ser escribibles desde los payloads cliente de los módulo `users` (`store` y `update`): los campos que aparezcan en la petición se ignoran y no se persisten, con el patrón `'saved' => false` ya usado por `hcaptcha_bypass`.
- Solo el flujo de recuperación del módulo `access` escribe esas columnas.
- Los códigos de recuperación almacenados son siempre hashes: un valor en texto plano enviado vía API nunca llega a persistir (lo que además preserva el contrato de `password_verify` del flujo `access/reset`).
- Sin cambios de respuesta ni de columnas: la semántica de columnas descrita en `access-recovery-fields` (qué escribe `access` al emitir y al consumir un código) se mantiene; la capacidad se modifica añadiendo requisitos, no alterando los existentes.

## Capabilities

### New Capabilities
- _(ninguna)_

### Modified Capabilities
- `access-recovery-fields`: se añade el requisito de que `recovery_code`/`recovery_date` no son escribibles desde payloads cliente de `users`, que solo el flujo `access` las escribe y que el valor almacenado es siempre un hash.

## Impact

- Código: `app/core/modules/users/controller.php:28-29` (añadir `'saved' => false` a ambos `$moduleFields`); `app/core/bootstrap/midelware.php` (`queryFields`, línea 458 — ya descarta `!saved`; sin cambios, solo verificación).
- API: los payloads de `POST /api/v5/users` (store) y del endpoint de actualización que incluyan `recovery_code`/`recovery_date` dejan de tener efecto (**BREAKING** para cualquier cliente que, pese al hallazgo, estuviera enviándolos — no hay uso legítimo conocido).
- Sin migraciones ni cambios en `app/core/config/`, ni en el flujo `access`.
- Tests: nuevas pruebas de mapeo de campos en `tests/` (patrón `UsersFieldMappingTest`/`AccessFieldMappingTest`).
