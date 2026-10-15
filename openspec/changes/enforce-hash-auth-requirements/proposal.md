# Proposal

## Why

La auditoría OWASP 2026-10-14 confirma el hallazgo **F-009 (Medio)**: `ModuleHandler::Authenticate()` (`app/core/auth/module.access.controller.php:49-73`) solo usa el hash alternativo para **eximir** de un requisito de autenticación (`if(!$auth || ($auth && $altAuth)) return true`), nunca para **exigirlo**. En `mailings` el método se declara `'index' => [false, NULL, ['hash' => $_GET["_key"]]]` (`app/core/modules/mailings/index.php:26`): con `auth=false` el hash se calcula y se ignora, y el endpoint es totalmente anónimo (de ahí que el XSS de F-008 sea explotable sin credenciales). En `users`, `'show' => [true, [1..5], ['hash' => true]]` (`app/core/modules/users/index.php:9`) declara un payload que jamás coincide con los tokens emitidos por el servidor: un marcador muerto que no aporta control. Además el payload declarado en `mailings` (`['hash' => $_GET["_key"]]`) no coincide con el payload con que se firman los enlaces (`HashAuth::Create([$code])` en `app/core/modules/access/local_login.php:309`), de modo que, aun existiendo el modo hash-requerido, los enlaces emitidos no validarían.

## What Changes

- Un método puede declararse **hash-requerido**: el endpoint rechaza las peticiones cuyo hash no valida, incluso cuando la autenticación por sesión está deshabilitada; los endpoints públicos tipo `mailings index` quedan accesibles solo con un hash válido.
- El payload declarado por el módulo y el payload con que el servidor emite los enlaces **deben alinearse**: los enlaces que el servidor emite para un endpoint deben validar contra la declaración de ese endpoint.
- Los marcadores muertos (p. ej. `['hash' => true]` en `users show`) no aportan ningún control y no deben apoyarse en ellos: los módulos deben declarar payloads reales que el servidor sepa emitir.
- **BREAKING**: los endpoints hoy anónimos quedan protegidos por hash — `mailings index` (la página `login-recovery`) solo será accesible con un hash válido, de modo que cualquier acceso directo sin enlace emitido por el servidor pasará a denegarse; los enlaces de recuperación emitidos por `loginMailer` deben llevar un hash alineado para seguir funcionando.

## Capabilities

### New Capabilities

- _(ninguna)_

### Modified Capabilities

- `hash-authentication`: se añaden requisitos de hash-requerido (rechazo cuando el hash no valida con autenticación deshabilitada), de alineación entre payload declarado y payload emitido en los enlaces, y de declaraciones de payload reales (los marcadores muertos no son control).

## Impact

- Código: `app/core/auth/module.access.controller.php:49-73` (semántica de validación de hash), `app/core/modules/mailings/index.php:26` (declaración del método), `app/core/modules/users/index.php:9` (marcador muerto), `app/core/modules/access/local_login.php:309` (`loginMailer`, emisión de `_key`).
- API: `GET /api/v5/mailings/login-recovery` deja de ser anónimo (denegado sin hash válido, incluido el acceso directo sin parámetros); el resto de endpoints con sesión no cambian.
- Tests: extender `tests/ModuleHashAuthTest.php` y `tests/HashAuthTest.php` (patrón de escenarios en subproceso ya usado allí).
