# Design

## Context

Ver `proposal.md` (Why) para la motivación. Solo el contexto necesario para decidir:

- `app/core/modules/config/index.php` declara hoy `index => [false, NULL]` (anónimo) y `show/store/update/destroy => [true, [1,2,3]]`. La visibilidad por fila tiene que convivir con eso: el listado es el único punto por el que se cuelan las filas privadas, pero el detalle también debe responder `404` para un id privado.
- `ModuleHandler::Authenticate()` (app/core/auth/module.access.controller.php:47) **return temprano** cuando `auth = false`: si el método no exige token, `USER_ROLE` nunca se define aunque el request lo traiga. Un administrador llamando a `GET /config` sería anónimo para la lógica de visibilidad.
- `BaseModel::init()` (app/core/bootstrap/midelware.php:63) es el único punto por el que pasan los filtros de listado, detalle, conteo de paginación y `WHERE` de escrituras, y mezcla `$get_params['filters']` **después** de los filtros de query string, de modo que un filtro de módulo no se puede sobreescribir desde la URL.
- El trait `Crud` llama a `parent::get()`, así que declarar `index()`/`show()` en el módulo no intercepta nada: sobreescribir `get()` en `AppConfig` no se ejecutaría.
- `queryFields()` (midelware.php:415) usa `empty()`: con `is_private = 0` la tienda lo reemplaza por el `default` y el `update` lo salta, así que hoy sería imposible marcar una fila como pública.
- El validador `boolean` (app/core/validation/fields.php:251) devuelve error para `0` y `false`: una regla `'is_private' => 'boolean'` impediría justamente el caso que hay que habilitar.
- Las migraciones SQL viven en `resources/migrations/` (por ejemplo `hcaptcha_bypass.sql` sobre `dev_users`) y los dumps de entorno en `app/karewa_dev_dev.sql` y `resources/karewa_dev.sql`.

## Goals / Non-Goals

**Goals**

- Un solo mecanismo de visibilidad por fila, declarado por el módulo, que cubra listado, detalle, paginación y escrituras sin tocar cada método.
- Que el rol de un administrador se respete en endpoints anónimos sin cambiar quién tiene acceso.
- Que el contrato OpenAPI se regenere desde el código, no a mano.
- Cero cambio de comportamiento para los demás módulos y campos.

**Non-Goals**

- Ocultar columnas dentro de una fila (eso ya lo hace `listed => false`, p. ej. `users.password`).
- Aplicar la visibilidad por fila a otros módulos; solo `config` la declara.
- Enmascarar el `value` de `smtp_config` en las respuestas de la API de config (queda como pregunta abierta en el change `smtp-from-config-table`).
- Arreglar el validador `boolean` para que acepte `0`.

## Decisions

### 1. La visibilidad se declara en `$get_params` y se aplica en `init()`

`$get_params['visibility'] = ['column' => 'is_private', 'roles' => [1, 2, 3]]`. Cuando la declaración existe y `USER_ROLE` no está en la lista (o no está definido), `init()` añade `['is_private', 0, '=']` a los filtros **antes** de devolverlos.

- **Por qué:** `init()` es el cuello de botella común: el listado, el detalle, el `count` de paginación y el `WHERE` de `post/put/delete` salen todos de ahí, y los filtros de módulo pisan a los del query string, así que un caller no puede quitar el filtro con `?is_private=eq:1`.
- **Alternativa descartada:** sobreescribir `index()`/`show()` en `AppConfig`. El trait `Crud` invoca `parent::get()` (a `BaseModel`), por lo que un `get()` propio en el módulo nunca se ejecutaría, y habría que repetir la lógica en cada verbo.

### 2. `show` pasa a anónimo y el filtro hace el trabajo

`'show' => [false, NULL]` en `index.php`. El detalle de una fila privada para un caller sin rol cae en el `404000` que `BaseModel::get()` ya devuelve cuando la consulta no trae filas.

- **Por qué:** es lo que pide el comportamiento "pública, solo para ver": las filas con `is_private = 0` tienen que ser legibles sin token. Proteger con roles en el método impediría justo eso.
- **Alternativa descartada:** mantener `show => [true, [1,2,3]]` y filtrar solo en el listado. Ocultaría las filas públicas a los clientes anónimos y contradice la regla elegida; además devolvería `401` en lugar del `404` acordado para las privadas.

### 3. Autenticación oportunista en `ModuleHandler::Authenticate()`

Cuando `auth = false` y el request trae un token, se llama a `SessionSet::ValidateOptional()` (nuevo método): si el token es válido y su sesión sigue activa se definen `AUTHENTICATED = true` y `USER_ROLE`; si falta, expiró, está mal formado o su sesión ya no es la activa, devuelve `false` y el método sigue como anónimo sin devolver error.

- **Por qué:** sin esto los roles 1, 2 y 3 no podrían ver filas privadas en `GET /config`, que es anónimo. El endpoint es público de todos modos, así que rechazar un token inválido no añade seguridad: solo rompería clientes que manden un token caducado a cualquier endpoint público (`hcaptcha`, `mailings`, `roles`).
- **Por qué `ValidateOptional` y no `Validate()`:** `SessionSet::Validate()` termina la petición con `ApiResponse::Set()` (`die()`) cuando el token no decodifica o su sesión no es la activa, por lo que un `try/catch` a su alrededor no atrapa nada. El método nuevo comparte la misma validación (misma decodificación, misma comprobación de sesión activa y de lista negra) mediante `applySession()`, pero devuelve `false` en vez de terminar, y no cancela ni pone en la lista negra la sesión presentada en un endpoint público.
- **Alternativas descartadas:** `Validate()` dentro de `try/catch` (no cubre el `die()`), responder `401` con token inválido (agrega un fallo nuevo en endpoints públicos) o validar el token dentro del módulo `config` (duplicaría la lógica de sesión y se olvidaría en el próximo módulo).

### 4. Marca por campo para aceptar el `0`

`queryFields()` distingue presencia con `array_key_exists()` cuando el campo declara `'zero_is_value' => true`; el `default` solo se aplica si la clave no existe. Solo `config.is_private` lleva la marca.

- **Por qué:** hay que poder crear una fila pública (`is_private = 0`) y volver de privada a pública, y hoy `empty(0)` lo impide en los dos sentidos.
- **Alternativa descartada:** cambiar `empty()` por presencia en todos los campos. Rompería el contrato documentado del `PUT` ("solo se actualizan los campos enviados con valor no vacío") para todos los módulos.

### 5. Sin regla de validación para `is_private`

El módulo no agrega `'is_private' => 'boolean'` a `$rules`. El validador actual rechaza `0`/`false`, así que la regla bloquearía exactamente la operación que este cambio habilita; sin regla, el campo se persiste tal cual llega.

- **Alternativa descartada:** arreglar el validador y añadir la regla. Cambia el comportamiento de validación de todos los módulos que ya usan `boolean` y queda fuera del alcance.

### 6. El OpenAPI sale del generador, con descripción por módulo

`tools/generate-openapi.php` reconoce `is_private` como booleano (tipo, ejemplo `true` y normalización del `default` numérico a `true`) y lleva un mapa de descripciones por módulo para escribir la regla de visibilidad en `GET /config` y `GET /config/{id}`. Después se regeneran las dos copias del contrato.

- **Por qué:** `tests/OpenApiSyncTest.php::testPublishedSpecMatchesRegeneratedOutput` regenera y compara; cualquier edición manual del JSON revienta la suite.
- **Alternativa descartada:** editar `api/docs/openapi.json` a mano.

## Risks / Trade-offs

- [Tras la migración el listado anónimo queda vacío] → es el BREAKING documentado; cada entorno abre a mano las filas que deban ser públicas (`UPDATE dev_config SET is_private = 0 WHERE slug = ...`).
- [Marcar una fila como pública expone su `value` completo a cualquiera] → es la intención del requisito; la migración deja **todas** las filas privadas y `smtp_config` nunca debe abrirse (se verifica en las tareas).
- [Administradores que no ven las filas privadas si el token no se valida en endpoints anónimos] → decisión 3, cubierta por un test con JWT firmado siguiendo el patrón de `tests/SessionSetTest.php`.
- [Token inválido ignorado en endpoints públicos] → sin pérdida de seguridad (el endpoint no exige credenciales); quien necesita saber que su token es viejo lo descubrirá en un endpoint autenticado.
- [La marca `zero_is_value` amplía el contrato interno de `$moduleFields`] → solo la declara `config`; los demás campos conservan la semántica de `empty()`.
- [Dumps y base de datos real divergen] → la migración es la fuente de verdad; los dumps se actualizan para que los entornos nuevos nascan con la columna.

## Migration Plan

1. Ejecutar `resources/migrations/config_is_private.sql` en cada entorno (la columna nace con `DEFAULT 1`, así que las filas existentes quedan privadas).
2. Verificar que `smtp_config` quedó con `is_private = 1` antes de continuar.
3. Desplegar el código y regenerar el contrato OpenAPI.
4. Abrir como públicas (`is_private = 0`) las filas que cada entorno quiera exponer.
5. **Rollback:** revertir el código; la columna se puede dejar en la tabla, es inerte si nada la lee. Los dumps vuelven a su versión anterior si se revert también el commit.

## Open Questions

- ¿Qué filas deben quedar públicas en cada entorno (`mailing-config`, por ejemplo)? Depende de datos, no de la especificación; se decide al desplegar.
- ¿Debe aplicarse el mismo mecanismo a otros módulos con datos sensibles? Hoy solo `config` lo declara; si aparece otro, el requisito se agregaría en su propia capacidad.
