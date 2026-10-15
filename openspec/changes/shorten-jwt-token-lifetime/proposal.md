# Proposal

## Why

La auditoría OWASP 2026-10-14 confirma el hallazgo **F-004 (Alto)**: el login invoca siempre `SessionSet::Login($login_data, true)` (app/core/modules/access/local_login.php:91), de modo que **todo** token de acceso dura 1 año (31536000 s) e ignora `SESSION_TIME` (86400 s). Adicionalmente el rol se lee del claim `role_id` del JWT (app/core/auth/jwt_token.php, app/core/auth/session.set.php) y no se revalida contra la base de datos en cada petición: una democión o baja de un usuario no revoca el token vigente, de modo que un ex-administrador conserva permisos de admin hasta expirar el token.

## What Changes

- El access token emitido en el login respeta el tiempo de sesión configurado (`SESSION_TIME`, 86400 s) en lugar de una vida fija de 1 año.
- La vida extendida de sesión (`keep_session`) solo se aplica cuando el usuario lo solicita explícitamente en el login; por defecto no se aplica.
- En cada petición autenticada se revalida el rol y el estado (`role_id`/`status_id`) del caller contra la base de datos: si cambiaron respecto al token, la sesión activa se revoca y la petición se rechaza, de modo que una democión surte efecto antes de que expire el token.
- **BREAKING**: los tokens se emiten con vida de 24 h en lugar de 1 año; los clientes que dependan de tokens de un año deberán reautenticarse (o solicitar explícitamente la sesión prolongada). Los tokens ya emitidos con expiración a 1 año siguen siendo criptamente válidos hasta su expiración (véase design.md — Migration Plan).

## Capabilities

### New Capabilities

- _(ninguna)_

### Modified Capabilities

- `session-management`: nuevo requisito de vida del access token ligada a la sesión configurada, con extensión solo por opt-in explícito del usuario.
- `authentication-and-authorization`: nuevo requisito de revalidación del rol/estado del caller en cada petición autenticada, con revocación de la sesión cuando difieren del token.

## Impact

- Código: `app/core/modules/access/local_login.php:91` (llamada de login fija en `keep_session=true`), `app/core/auth/jwt_token.php:38-42` (cálculo de `exp`), `app/core/auth/session.set.php` (`Login`/`Validate`), `app/core/bootstrap/midelware.php` y `app/core/auth/module.access.controller.php` (punto de validación por petición).
- API: respuesta del login (`expires_in` pasa a `SESSION_TIME` salvo opt-in) y comportamiento de peticiones autenticadas con rol/estado cambiados (ahora rechazadas con sesión revocada).
- Frontend (repo separado): si ofrece "recordarme", deberá enviar el opt-in explícito.
- Tests: `tests/JWTKeyEncodeTest.php`, `tests/SessionSetTest.php` (extender).
