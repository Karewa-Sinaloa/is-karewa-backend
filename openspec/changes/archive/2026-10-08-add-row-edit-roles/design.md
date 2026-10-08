# Design

## Context

Ver `proposal.md` (Why) para la motivación. Solo el contexto necesario para decidir:

- `app/core/modules/config/index.php` declara `'update' => [true, [1, 2, 3]]` y `'destroy' => [true, [1, 2, 3]]`: el gate de método ya deja pasar a 1, 2 y 3 y nada más, así que la columna solo tiene que discriminar **dentro** de ese conjunto. Los roles 4 y 5 desaparecen del modelo y quedan fuera de este cambio.
- `BaseModel::init()` (app/core/bootstrap/midelware.php:63) es el punto por el que pasan todos los verbos antes de tocar la base de datos, y ahí ya está `$this->entryId` resuelto desde `$_GET['id']`.
- `queryFields()` (midelware.php:415) descarta del `INSERT`/`UPDATE` todo campo con `saved => false`, y el `INSERT` no lleva la columna si no está en la lista, por lo que MySQL aplica el `DEFAULT` de la tabla.
- `DBGet::Get()` con un solo filtro `id` devuelve la fila, y los operadores disponibles son `eq, lt, gt, gte, lte, ne, LIKE, IN, IS_NULL, NOT_NULL, OR` (app/core/model/get.php:75) — no hay `FIND_IN_SET`, y los tests del ORM corren sobre SQLite in-memory (`tests/Support/OrTestCase.php`).
- El change `add-config-is-private` (planificado, sin implementar) toca los mismos archivos: `$moduleFields`/`$get_params` de `config` y `init()`.

## Goals / Non-Goals

**Goals**

- Que cada fila de `config` declare qué roles pueden modificarla, configurable sin redeploy y con el rol 1 como piso de rescate.
- Un mecanismo declarativo en `$get_params` que otro módulo pueda copiar sin reescribir lógica.
- Cero cambio de comportamiento para las filas existentes y cero cambio en el contrato de escritura que ya conocen los clientes.
- Que el contrato OpenAPI y el README describan el mismo modelo.

**Non-Goals**

- Gestionar `edit_roles` desde la API (queda en `saved => false`; exponerlo como escribible es un cambio aparte).
- Validar que los ids de la lista existan en la tabla `roles`: se escribe a mano y un id desconocido simplemente nunca coincide.
- Aplicar el mecanismo a otros módulos; solo `config` lo declara.
- Borrar los roles 4 y 5 de la tabla `roles` o migrar usuarios con esos roles.
- Gobernar la lectura de filas: eso lo hace `is_private` (change `add-config-is-private`).

## Decisions

### 1. Columna con lista de roles, declarada en `$get_params`

`edit_roles VARCHAR(32) NOT NULL DEFAULT '1,2,3'`, y en el módulo `'edit_roles' => ['column' => 'edit_roles', 'always' => [1]]`.

- **Por qué:** una fila ya existe en la tabla, no cruza con nada, se configura con un `UPDATE` y se lee con el mismo mecanismo que el resto de la fila. La forma declarativa (`column` + `always`) es la misma que usa `visibility` en el change `add-config-is-private`, así que otro módulo la adopta copiando una línea.
- **Alternativa descartada:** tabla hija `row_permissions(table, row_id, role_id, action)` con su módulo. Gana normalidad, integridad referencial y multi-acción, pero exige módulo nuevo (controller, `index.php`, reglas, entradas en el generador, OpenAPI, tests y migración) para tres filas; además la columna se puede migrar a tabla después con un script mecánico.

### 2. El chequeo se hace en PHP sobre la fila, no en SQL

En `init()`, cuando el verbo es `update` o `destroy` y el módulo declara `edit_roles`, se carga la fila por `id` (un `SELECT`), se parte la lista con `explode(',')` y se compara contra `USER_ROLE` o contra el piso `always`. Sin `SELECT` de por medio no hay ni forma de fallar.

- **Por qué:** el chequeo es por fila concreta y ya tenemos `entryId`; un `SELECT` extra por escritura es despreciable y funciona igual en MySQL y en el SQLite de los tests.
- **Alternativas descartadas:** filtro SQL `LIKE '%,3,%'` — exige normalizar la lista con comas envolventes, obliga a que el formato escrito a mano sea exacto y solo aporta si además filtramos lecturas, que no hacemos; `FIND_IN_SET` — no existe en SQLite ni en el mapa de operadores del ORM.

### 3. `listed => true`, `saved => false`, `filter => true`

El campo se devuelve en las respuestas y se puede usar como filtro, no entra en los bodies de `POST`/`PUT` y no se puede cambiar por API.

- **Por qué:** decisión pedida: por ahora se configura directamente en la base de datos, pero el front necesita ver quién puede editar una fila.
- **Alternativas descartadas:** `listed => false` (como `users.password`) deja al cliente a ciegas sobre los permisos; `saved => true` obligaría a definir formato, normalización y validación en la API, que es el alcance que se pospuso.

### 4. El gate de método no cambia

`store`, `update` y `destroy` siguen en `[true, [1, 2, 3]]`. La columna solo restringe dentro de ese conjunto (`1,2,3`, `1,2`, `1`).

- **Por qué:** los roles 4 y 5 desaparecen, así que abrir el gate aportaría cero y cambiaría el código de rechazo actual (`901004`) para llamadas que ya se rechazan.
- **Alternativa descartada:** abrir `update`/`destroy` a cualquier rol autenticado y dejar que la columna decida — necesaria solo si 4 y 5 siguieran vivos.

### 5. Denegación con un código nuevo `901009`

`901009: message: Not allowed to modify this entry, code: APP_AUTH_ROW_FORBIDDEN, http_code: 403` en `app/core/config/api_codes.yml`.

- **Por qué:** es un fallo de autorización por recurso y merece su propio mensaje en los logs y en el contrato.
- **Alternativas descartadas:** `901004` (`APP_AUTH_FAILED`, mensaje "Login failed") confunde al cliente; `404000` ocultaría una fila que la lectura pública sí devuelve, incoherente con `is_private`.

### 6. `store` no se chequea

Al no ser escribible, la columna no entra al `INSERT` y aplica el `DEFAULT '1,2,3'` de la base de datos.

- **Por qué:** cero lógica para el caso de creación y garantía de que ninguna fila nace sin lista.
- **Alternativa descartada:** rellenar el valor desde `queryFields()` — repetiría en PHP un default que la columna ya garantiza.

### 7. Orden de aplicación con el cambio `add-config-is-private`

Primero `add-config-is-private` y luego este. Ambos agregan claves a `$moduleFields`/`$get_params` de `config` y líneas dentro de `init()`.

- **Por qué:** aplicarlos en paralelo obliga a fusionar los mismos bloques de código a mano.
- **Alternativa descartada:** un solo change con las dos funcionalidades — mezclaría la visibilidad por lectura con la autorización por escritura, que se aprueban y revisan por separado.

## Risks / Trade-offs

- [La lista se escribe a mano con un formato distinto (`1 2 3`, `1|2`)] → el parseo no encuentra el rol y solo el rol 1 podrá editar; el piso de la decisión 1 es el rescate. Se documenta el formato exacto en la migración y en el README.
- [Un `SELECT` extra en cada `update`/`destroy`] → despreciable frente al `UPDATE` que viene después.
- [La lista de permisos queda visible para los clientes autenticados] → es información de control, no un secreto; el que no debe editar igual recibe `403`.
- [Dos changes tocan los mismos archivos] → se aplican en orden (decisión 7).
- [El README documenta la columna antes de que exista] → la tarea 1.1 crea la columna en la misma tanda; la tarea 3.3 verifica que la sección del README describe lo implementado.

## Migration Plan

1. Ejecutar `resources/migrations/config_edit_roles.sql` en cada entorno: la columna nace con `DEFAULT '1,2,3'`, por lo que todas las filas existentes conservan el comportamiento actual.
2. Desplegar el código.
3. Restringir a mano las filas que lo requieran, por ejemplo `UPDATE dev_config SET edit_roles = '1,2' WHERE slug = 'smtp_config';` y `SET edit_roles = '1'` para las reservadas al administrador.
4. Verificar con cada rol: `2` edita una fila `1,2,3`, `3` recibe `901009` en una fila `1,2`, `1` edita cualquier fila.
5. **Rollback:** revertir el código; la columna se puede dejar en la tabla, es inerte si nada la lee.

## Open Questions

- ¿Exponer `edit_roles` como campo escribible por la API (`PUT /config/{id}`) más adelante, con formato y validación propios? Depende de cuándo se quiera administrar por interfaz.
- ¿Extender el mismo mecanismo a otros módulos? El diseño ya lo permite con una línea en `$get_params`, pero hoy solo `config` lo declara.
