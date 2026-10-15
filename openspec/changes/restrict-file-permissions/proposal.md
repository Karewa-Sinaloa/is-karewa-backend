# Proposal

## Why

La auditoría OWASP 2026-10-14 confirma el hallazgo **F-011 (Medio)**: la clave privada JWT del worktree está en `-rw-r--r--` (0644) pese a que `setup.sh:34` ordena `chmod 600` — Git solo conserva el bit de ejecución, así que cualquier checkout la vuelve world-readable —; `logs/` y sus archivos están en 0777 (`log.manager.php:15` hace `mkdir(DEBUG_LOG_PATH, 0777)` y la línea 19 hace `chmod` sobre el *handle* devuelto por `fopen`, por lo que la corrección nunca llega a la ruta); y las carpetas de subida se crean con `permissions => 0777` (`image-upload/controller.php:17`, `attachments/controller.php:17`).

## What Changes

- `app/.keys/jwtRS256.key` queda en **0600** y `app/.keys/` en **0700**, y la restricción se restablece en cada instalación (no solo la primera vez).
- El directorio de logs queda en **0750** y los archivos de log en **0640**; se elimina el uso de 0777 y el `chmod` pasa a aplicar sobre la **ruta** (hoy se aplica al handle de archivo, por lo que no surte efecto).
- Las carpetas de subida se crean con **0755** en lugar de 0777.
- Se añade una verificación de deriva de permisos ejecutable con un solo comando del proyecto (`make check-perms`) que informa ruta, modo encontrado y modo esperado.
- **BREAKING** (solo para despliegues): procesos de otros usuarios del sistema que dependieran de los permisos world-writable de `logs/` o de las carpetas de subida dejan de poder leerlos/escribirlos; sin cambios en la API ni en la respuesta HTTP.

## Capabilities

### New Capabilities

- `filesystem-permissions`: ficheros y directorios sensibles se crean y se mantienen con permisos de sistema de archivos de mínimo privilegio.

### Modified Capabilities

- _(ninguna)_

## Impact

- Código: `app/core/helpers/log.manager.php:15-19`, `app/core/modules/image-upload/controller.php:17`, `app/core/modules/attachments/controller.php:17`, `setup.sh:31-35`.
- Worktree/despliegue: `app/.keys/`, `logs/`, carpetas bajo `statics/uploads/`.
- Verificación: `Makefile` (objetivo `check-perms` + línea en `help`), `README.md` / `SECURITY.md`.
- Pruebas: test nuevo de permisos bajo `tests/` (patrón de bootstrap de `openspec/specs/testing`).
- Fuera de alcance (pertenecen a `improve-security-logging`, F-015): el **contenido** y la privacidad de los logs.
