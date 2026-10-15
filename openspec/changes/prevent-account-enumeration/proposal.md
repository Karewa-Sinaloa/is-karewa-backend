# Proposal

## Why

El hallazgo F-013 de la auditoría (`docs/auditoria-seguridad/auditoria-owasp-2026-10-14.md`) demuestra que la API distingue cuentas existentes de inexistentes tanto en contenido como en tiempo: `Recovery()` responde `404000` cuando el correo no existe (o está inactivo) frente a `SUCCESS` cuando existe, y en `Login()` el `password_verify` solo se ejecuta si el usuario existe, dejando una huella temporal medible. Un atacante puede enumerar cuentas registradas a 10 peticiones/minuto (límite `sensitive_limit`), lo que facilita ataques de credenciales, phishing dirigido y abuso del flujo de recuperación.

## What Changes

- El endpoint de recuperación (`access/Recovery`) responde **exactamente lo mismo** —mismo código, mensaje y campos— para correos desconocidos, inactivos y conocidos: siempre una respuesta de éxito simulado, sin enviar correo real si no hay cuenta.
- El endpoint de recuperación aplica un **tiempo mínimo de respuesta** a todas las ramas y ejecuta en la rama "cuenta inexistente" trabajo de verificación de coste equivalente, para que la latencia no delate la ausencia de la cuenta.
- El login (`access/Login`) iguala el tiempo de respuesta cuando el usuario no existe: se ejecuta una comparación de hash contra un valor falso (*dummy hash comparison*) con el mismo algoritmo y coste que los hashes almacenados, de modo que la rama "usuario no existe" no sea distinguible por cronometraje.
- Ningún otro campo de la respuesta (mensaje, `role_id`, `session`, errores de validación) difiere en función de la existencia de la cuenta.
- **BREAKING**: clientes o scripts que usaban `404000` en `Recovery` para detectar correos no registrados dejarán de poder hacerlo (ese comportamiento era el propio hallazgo).
- Se especifica como nueva capacidad `account-enumeration-resistance`; no se modifican capacidades existentes (el contrato de columnas de `access-recovery-fields` y la validación de `authentication-and-authorization` no cambian).

## Capabilities

### New Capabilities
- `account-enumeration-resistance`: los flujos de autenticación y de recuperación responden de forma idéntica, en contenido y en tiempo, con independencia de que la cuenta exista o no.

### Modified Capabilities
- _(ninguno)_

## Impact

- Código: `app/core/modules/access/local_login.php` — `Recovery()` líneas 160-162 (rama `404000`), mínimo de tiempo de respuesta y verificación simulada en la rama de cuenta inexistente; `Login()` rama de usuario inexistente (línea 41 en adelante) para el dummy hash.
- API: respuesta del endpoint `access/Recovery` para correos desconocidos pasa de `404000` a éxito simulado (cambio de comportamiento observable, **BREAKING** para quien consumiera ese error).
- Tests: nuevas pruebas de contenido y de tiempo en `tests/` (patrón `AccessLogoutTest`/`AccessFieldMappingTest`, SQLite en memoria); ninguna prueba existente depende del `404000` de Recovery (verificar en `./vendor/bin/phpunit`).
- Sin cambios en BD, migraciones, plantillas de correo ni en el frontend (que ya solo muestra un mensaje genérico).
