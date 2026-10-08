# Proposal

## Why

La tabla `config` mezcla ajustes que deben ser públicos (por ejemplo `mailing-config`) con entradas que guardan credenciales (`smtp_config` trae contraseñas SMTP), y hoy `GET /config` es anónimo y devuelve **todas** las filas: cualquier visitante lee las credenciales. Hace falta distinguir en cada fila si es privada o pública para poder exponer la API sin filtrar secretos.

## What Changes

- Nueva columna `is_private TINYINT(1) NOT NULL DEFAULT 1` en la tabla `config`, con migración en `resources/migrations/config_is_private.sql` que también deja en `1` todas las filas existentes (privadas por defecto, se abren a mano).
- El módulo `config` declara `is_private` en su `$moduleFields` (`listed`, `filter`, `saved`, `default => 1`), de modo que aparece como filtro de consulta, como propiedad de los bodies `POST`/`PUT`, en las respuestas y en el contrato OpenAPI.
- Visibilidad por fila: un caller sin roles 1, 2 o 3 solo ve las filas con `is_private = 0`; una fila privada no existe para él (`GET /config/{id}` responde `404000`, igual que un id inexistente) y no aparece en el listado ni en el conteo de paginación.
- `GET /config/{id}` deja de exigir token (`show` pasa a anónimo) para que las filas públicas sean visibles para todos; `POST`, `PUT` y `DELETE /config` siguen reservados a los roles 1, 2 y 3.
- Autenticación oportunista: cuando un método no exige token pero el request presenta uno, se valida igual y su rol se hace valer; así los roles 1, 2 y 3 siguen viendo las filas privadas en endpoints públicos. Un token ausente o inválido se trata como anónimo (el endpoint es público, no se agrega un rechazo nuevo).
- Corrección del guardado de valores `0`: hoy `queryFields()` usa `empty()` y descarta `is_private = 0` (y lo pisa con el default `1`), así que no se podría marcar una fila como pública. Se agrega una marca por campo para que `0` cuente como valor presente.
- OpenAPI: se regenera `api/docs/openapi.json` y `httpdocs/api/docs/openapi.json` con `is_private` documentado como booleano (default `true`), `GET /config/{id}` sin esquema de seguridad y los nuevos escenarios de visibilidad.
- **BREAKING**: el listado anónimo `GET /config` pasa de devolver todas las filas a devolver solo las públicas; con la migración aplicada eso significa lista vacía hasta que se marquen filas como `is_private = 0`. `GET /config/{id}` sin token pasa de `401` a `404` cuando la fila es privada.

## Capabilities

### New Capabilities

- Ninguna.

### Modified Capabilities

- `config-module-fields`: gana la columna `is_private` (campo expuesto, con default al crear) y el requisito de visibilidad por fila: privadas solo para roles 1, 2 y 3 con `404` para el resto, públicas legibles por cualquiera y editables solo por 1, 2 y 3.
- `authentication-and-authorization`: el método de autenticación acepta token opcional — un token presentado en un método que no lo exige se valida y su rol se hace valer, sin cambiar el acceso del método.
- `swagger-scalar-api-docs`: el contrato documenta `is_private` en el módulo `config` y `GET /config/{id}` como operación pública, coherente con `$accepted_methods`.

## Impact

- `app/core/modules/config/controller.php` — `is_private` en `$moduleFields` y declaración de visibilidad en `$get_params`.
- `app/core/modules/config/index.php` — `show` deja de exigir roles.
- `app/core/bootstrap/midelware.php` — inyección del filtro de visibilidad en `init()` (cubre listado, detalle, conteo y escrituras) y aceptación del `0` en `queryFields()`.
- `app/core/auth/module.access.controller.php` — validación oportunista del token presente cuando el método no lo exige.
- `resources/migrations/config_is_private.sql` — nueva migración; `app/karewa_dev_dev.sql` y `resources/karewa_dev.sql` — columna en los dumps.
- `tools/generate-openapi.php` — `is_private` como tipo booleano con ejemplo `true`; regeneración de ambas copias del contrato.
- Tests — visibilidad de filas, guardado de `0`, autenticación oportunista y sincronización del OpenAPI (`tests/OpenApiSyncTest.php`, `tests/ConfigFieldMappingTest.php`).
