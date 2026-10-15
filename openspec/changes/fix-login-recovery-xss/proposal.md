# Proposal

## Why

La auditoría OWASP 2026-10-14 confirma el hallazgo **F-008 (Medio)**: el endpoint público `mailings/login-recovery` responde `Content-Type: text/html` y construye `login_recovery_link` concatenando `$_GET['recovery_hash']` dentro de un atributo `href` de la plantilla Smarty sin escapar (`Smarty::$escape_html = false` por defecto). `strip_tags()` no neutraliza atributos, de modo que un valor como `recovery_hash=" onclick=alert(1) x="` produce JavaScript ejecutable en el origen del API, sin necesidad de autenticación (véase F-009). Evidencia: `app/core/modules/mailings/login_recovery.php:17,31` y `app/templates/views/login_recovery.tpl:12`.

## What Changes

- Toda salida HTML de la plantilla de recuperación escapa los valores controlados por el usuario, incluidos los situados en atributos como `href`, de modo que el XSS reflejado es imposible.
- `recovery_hash` (y los demás parámetros que componen el enlace de recuperación) se validan contra su formato estricto esperado y se rechazan con un error cuando no lo cumplen.
- Un valor manipulado (comilla + manejador de eventos, o etiqueta `<script>`) nunca llega al HTML renderizado como marcado ejecutable.
- Sin cambio en el contrato de éxito: un enlace legítimo sigue devolviendo `text/html` con el enlace de reset navegable; solo los valores malformados pasan a responder error.

## Capabilities

### New Capabilities

- `mailing-html-rendering`: garantiza que las respuestas HTML producidas por el módulo `mailings` escapan el input controlado por el usuario y validan los parámetros del enlace, haciendo imposible el XSS reflejado.

### Modified Capabilities

- _(ninguna)_

## Impact

- Código: `app/core/modules/mailings/login_recovery.php` (líneas 17 y 31), `app/templates/views/login_recovery.tpl` (línea 12), posiblemente `app/core/modules/mailings/smarty_settings.php` (configuración de escape del render).
- API: `GET /api/v5/mailings/login-recovery?...` — con parámetros malformados deja de devolver HTML renderizado y responde un error de validación; con parámetros válidos el HTML escapa los valores.
- Tests: test nuevo (p. ej. `tests/MailingsTemplateEscapingTest.php`) sobre render escapado y rechazo de valores manipulados.
