# Proposal

## Why

Los permisos de edición viven hoy solo en `$accepted_methods`, que decide por **módulo y método** quién puede escribir: en `config` eso deja a los roles 1, 2 y 3 con el mismo poder sobre todas las filas. Hace falta granularidad **por fila**: unas filas editables por 1, 2 y 3, otras solo por 1 y 2, otras solo por el rol 1, configurable sin tocar código.

## What Changes

- Nueva columna `edit_roles VARCHAR(32) NOT NULL DEFAULT '1,2,3'` en la tabla `config`, con migración en `resources/migrations/config_edit_roles.sql`; las filas existentes quedan con `1,2,3`, es decir, con el comportamiento de escritura que existe hoy.
- El módulo `config` declara el campo como **legible pero no escribible** (`listed => true`, `saved => false`, `filter => true`): aparece en `GET /config` y como filtro, no en los bodies de `POST`/`PUT`, y por ahora se configura editando directamente la base de datos.
- La declaración de visibilidad/edición vive en `$get_params` con forma genérica (`'edit_roles' => ['column' => 'edit_roles', 'always' => [1]]`), de modo que otro módulo pueda adoptarla sin repetir lógica.
- Antes de `update` o `destroy`, el módulo comprueba que `USER_ROLE` esté en la lista de la fila, o que sea el rol 1 (piso anti-bloqueo: el administrador siempre puede editar). Si no, responde con un código nuevo `901009` (`APP_AUTH_ROW_FORBIDDEN`, HTTP `403`) sin tocar los datos.
- El gate de método de `config` **no cambia** (`store`, `update`, `destroy` siguen en `[true, [1, 2, 3]]`): la columna discrimina dentro de ese conjunto, con listas como `1,2,3`, `1,2` o `1`. Los roles 4 y 5 desaparecen del modelo, cosa que queda fuera de este cambio.
- `store` no requiere chequeo: como el campo no es escribible, no entra al `INSERT` y aplica el `DEFAULT` de la columna.
- El contrato OpenAPI documenta `edit_roles` como campo legible de `config`, ausente de los bodies de escritura, y agrega la respuesta `403` a las operaciones de escritura del módulo.
- El README documenta cómo configurar estos permisos desde la base de datos (formato de la lista, default y piso del rol 1).
- **BREAKING**: ninguno. El default `1,2,3` reproduce el comportamiento actual; solo las filas que se restrinjan manualmente empiezan a devolver `403`.

## Capabilities

### New Capabilities

- Ninguna.

### Modified Capabilities

- `config-module-fields`: gana el requisito de permisos de edición por fila — la columna `edit_roles` declara qué roles pueden modificar cada fila, el rol 1 siempre puede, el resto responde `901009`, el campo se lee por API pero solo se escribe en la base de datos, y una fila nueva nace con `1,2,3`.
- `swagger-scalar-api-docs`: el contrato documenta `edit_roles` como campo legible de `config` sin presencia en los bodies, y las operaciones de escritura de `config` documentan la respuesta `403` de permiso por fila.

## Impact

- `resources/migrations/config_edit_roles.sql` — nueva migración; `app/karewa_dev_dev.sql` y `resources/karewa_dev.sql` — columna en los dumps.
- `app/core/modules/config/controller.php` — `edit_roles` en `$moduleFields` y la declaración en `$get_params`.
- `app/core/bootstrap/midelware.php` — chequeo de permisos por fila en `init()` para `update`/`destroy`.
- `app/core/config/api_codes.yml` — nuevo código `901009` (`APP_AUTH_ROW_FORBIDDEN`, `403`).
- `tools/generate-openapi.php` — `edit_roles` documentado y `901009` en la selección de códigos de escritura; regeneración de `api/docs/openapi.json` y `httpdocs/api/docs/openapi.json`.
- `README.md` — sección que explica cómo configurar los permisos por fila desde la base de datos.
- Tests — `ConfigFieldMappingTest` ampliado, `tests/ConfigEditRolesTest.php` nuevo y `tests/OpenApiSyncTest.php` para el contrato.
