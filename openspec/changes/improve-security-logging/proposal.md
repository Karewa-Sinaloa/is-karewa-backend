# Proposal

## Why

El hallazgo F-015 de la auditoría OWASP 2026-10-14 (`docs/auditoria-seguridad/auditoria-owasp-2026-10-14.md`) confirma que los eventos de seguridad no se registran de forma fiable: los eventos útiles existen (401/403, fallos JWT) pero sin un conjunto mínimo garantizado, el código de recuperación de contraseñas viaja en claro por la query string de la URL que el servidor descarga (queda en access logs de proxies y del host de mailings), y un fallo de escritura del log mata la petición con un `die` que revela la ruta interna del fichero de log. Sin registros correlacionables y sin secretos fuera de las URLs, un incidente no puede detectarse ni investigarse.

## What Changes

- Se define un conjunto mínimo de eventos de seguridad que DEBEN quedar registrados (fallos de autenticación, denegaciones de autorización, límites de tasa alcanzados, subidas rechazadas), con contexto suficiente para correlacionar (módulo, código de respuesta, identidad si existe) y **sin secretos** en el mensaje.
- Se prohíbe que secretos o códigos de recuperación aparezcan en URLs de query string: el enlace de recuperación enviado por correo llevará solo un hash de un solo uso, nunca el código ni un payload que lo contenga en claro.
- Se elimina la fuga de información del fallo de logging: un error de escritura de log ya no termina la petición ni revela la ruta del fichero al cliente.
- **BREAKING** el enlace de recuperación por correo deja de llevar `code=` en la query string: el frontend y la plantilla de mailings deben obtener el código solo a través del hash de un solo uso (ver `design.md`, riesgo de coordinación con los cambios `fix-login-recovery-xss` y `harden-recovery-code`).
- Las rutas de log de `app/core/helpers/log.manager.php` que hoy registran la URL completa con `code=` dejan de hacerlo.

## Capabilities

### New Capabilities

<!-- none -->

### Modified Capabilities

- `openspec/specs/configuration-and-logging`: se añaden requisitos de comportamiento sobre registro de eventos de seguridad, ausencia de secretos en URLs y resiliencia ante fallos de logging; el requisito "Logging" existente no se modifica.

## Impact

- `app/core/helpers/log.manager.php` — mensajes de error de escritura (hoy `die(... 'no can write to log file ' . $file)`); entrada `error_logs()` como superficie única de registro.
- `app/core/helpers/api_response.php:44,54` — mismo patrón de `die()` con `ERROR_LOG_FILE` en la respuesta al cliente.
- `app/core/modules/access/local_login.php:316-319` — `loginMailer()` construye la URL de `mailings/login-recovery` con `code=` y la registra íntegra en el log si falla la plantilla.
- Generación del enlace de recuperación: `app/core/modules/mailings/` (`login_recovery.php` y el índice del módulo) — consumidor de `recovery_hash`/`code`.
- Superficie de emisión de eventos: autenticación (`app/core/auth/module.access.controller.php`, `app/core/modules/access/`), middleware de rol (`app/core/bootstrap/midelware.php`), rate limit (`app/core/helpers/rate_limit.php`) y subidas (`app/core/modules/image-upload/`, `app/core/modules/attachments/`).
- Sin cambios de contrato HTTP visible para clientes legítimos, salvo que el enlace de recuperación por correo deja de contener `code=` (ver **BREAKING** de integración con el frontend en `design.md`).
- No incluye permisos de ficheros de log (F-011, cambio `restrict-file-permissions`) ni infraestructura de alertado.
