# ORM Wiki — Karewa Backend

Este documento describe el funcionamiento completo del ORM personalizado del sistema Karewa. No se trata de un ORM externo (como Eloquent o Doctrine), sino de una capa de abstracción de base de datos construida a medida sobre PDO.

---

## Tabla de contenido

1. [Arquitectura general](#arquitectura-general)
2. [Conexión a la base de datos — `DB`](#conexión-a-la-base-de-datos--db)
3. [BaseModel — el corazón del ORM](#basemodel--el-corazón-del-orm)
4. [El trait `Crud`](#el-trait-crud)
5. [Definición de campos — `$moduleFields`](#definición-de-campos--modulefields)
6. [Definición de consultas — `$get_params`](#definición-de-consultas--get_params)
7. [Reglas de validación — `$rules`](#reglas-de-validación--rules)
8. [Operaciones CRUD](#operaciones-crud)
   - [GET (index / show)](#get-index--show)
   - [POST (store)](#post-store)
   - [PUT (update)](#put-update)
   - [DELETE (destroy)](#delete-destroy)
9. [Capas internas de base de datos](#capas-internas-de-base-de-datos)
   - [DBGet](#dbget)
   - [DBStore](#dbstore)
   - [DBUpdate](#dbupdate)
   - [DBDelete](#dbdelete)
10. [Filtros desde la URL](#filtros-desde-la-url)
11. [Búsqueda de texto — `?search=`](#búsqueda-de-texto--search)
12. [Paginación](#paginación)
13. [Ordenamiento y agrupación](#ordenamiento-y-agrupación)
14. [Validación de campos](#validación-de-campos)
15. [Opciones avanzadas del controlador](#opciones-avanzadas-del-controlador)
16. [Ejemplo completo de módulo](#ejemplo-completo-de-módulo)

---

## Arquitectura general

```
HTTP Request
  └─ httpdocs/api.php
       └─ app/core/bootstrap/init.php
            ├─ base.php         → constantes globales, conexión DB
            ├─ midelware.php    → determina REQUEST_TYPE
            └─ modules.php      → carga {modulo}/index.php
                  └─ {modulo}/index.php
                        ├─ {modulo}/controller.php  (extiende BaseModel)
                        └─ ModuleHandler::Validate() → llama al método del controlador
```

El ORM vive principalmente en:

| Archivo | Descripción |
|---------|-------------|
| `app/core/bootstrap/midelware.php` | Define `BaseModel` y el trait `Crud` |
| `app/core/model/conexion.php` | Clase `DB` — singleton de PDO |
| `app/core/model/get.php` | Clase `DBGet` — SELECT |
| `app/core/model/store.php` | Clase `DBStore` — INSERT |
| `app/core/model/update.php` | Clase `DBUpdate` — UPDATE |
| `app/core/model/delete.php` | `DBDelete` — DELETE |
| `app/core/validation/fields.php` | `FieldsValidator` — reglas de validación |

---

## Conexión a la base de datos — `DB`

**Namespace:** `App\Model\DB`  
**Archivo:** `app/core/model/conexion.php`

Crea una conexión PDO utilizando las constantes definidas en `base.php` (`MYSQL_HOST`, `MYSQL_DB`, `MYSQL_USER`, `MYSQL_PSWD`, `MYSQL_PORT`, `MYSQL_CHARSET`).

```php
$dbconn = DB::connection();
```

Características:
- Usa `PDO::ATTR_PERSISTENT = true` (conexiones persistentes).
- Modo de errores: `PDO::ERRMODE_EXCEPTION`.
- `PDO::ATTR_EMULATE_PREPARES = false` (sentencias preparadas nativas).
- Si falla, responde `HTTP 500` con JSON y termina la ejecución.

---

## BaseModel — el corazón del ORM

**Namespace:** `App\Model\BaseModel`  
**Archivo:** `app/core/bootstrap/midelware.php`

Todos los controladores de módulo extienden esta clase. Define las propiedades configurables y los métodos CRUD de alto nivel.

### Propiedades principales

| Propiedad | Tipo | Descripción |
|-----------|------|-------------|
| `$moduleFields` | `array` | Definición de todos los campos del módulo |
| `$get_params` | `array` | Configuración base de la consulta SELECT |
| `$rules` | `array` | Reglas de validación para POST/PUT |
| `$table_assoc` | `array` | Tablas asociadas para verificar antes de un DELETE |
| `$methodOptions` | `array` | Opciones de comportamiento del método (`end` por defecto `true`) |
| `$queryFields` | `array\|null` | Campos adicionales que se agregan manualmente al INSERT/UPDATE |
| `$payload` | `stdClass` | Cuerpo de la petición (JSON decodificado) |

### Constructor

```php
function __construct(array $additionalData = [])
```

Carga `$_payload` (el JSON del request) en `$this->payload`. Si se pasan datos adicionales en `$additionalData`, se mezclan con el payload. Útil para generar campos derivados (como slugs) antes de guardar.

```php
// Ejemplo: generar slug automáticamente al crear
public function __construct() {
    global $_payload;
    $extra = [];
    if ($_payload && isset($_payload->name)) {
        $extra['slug'] = toAlphanumeric($_payload->name, '-');
    }
    parent::__construct($extra);
}
```

---

## El trait `Crud`

**Namespace:** `App\Model\Crud`  
**Archivo:** `app/core/bootstrap/midelware.php`

Proporciona los cinco métodos HTTP estándar como alias de los métodos internos de `BaseModel`:

```php
trait Crud {
    public function show()   { return parent::get(); }
    public function index()  { return parent::get(); }
    public function store()  { return parent::post(); }
    public function update() { return parent::put(); }
    public function destroy(){ return parent::delete(); }
}
```

Un controlador que lo use simplemente declara:

```php
class MiComponente extends BaseModel {
    use Crud;
    // ...
}
```

Si se necesita personalizar uno de los métodos, se puede renombrar el trait y luego llamarlo internamente:

```php
use Crud {
    store as protected traitstore;
}

public function store() {
    // lógica adicional...
    $this->traitstore();
}
```

---

## Definición de campos — `$moduleFields`

Esta propiedad es el **esquema del módulo**. Define qué campos existen, cómo se llaman en la base de datos y cómo se comportan en cada operación.

### Estructura

```php
protected $moduleFields = [
    'alias_api' => [
        'field'    => 'columna_db',   // Nombre real en la DB (puede incluir alias de tabla: u.email)
        'filter'   => true,           // Puede usarse como filtro URL (?alias_api=valor)
        'saved'    => true,           // Se escribe en INSERT/UPDATE
        'listed'   => true,           // Se incluye en el SELECT
        'default'  => null,           // Valor por defecto si el campo viene vacío
        'optional' => false,          // Si true, se omite en UPDATE cuando viene vacío
        'roles'    => false,          // false = todos; array de IDs = sólo esos roles
    ],
];
```

### Valores por defecto automáticos

Si un campo en `$moduleFields` no declara alguna de las claves anteriores, `BaseModel::controllerFields()` la rellena con el valor por defecto correspondiente. Solo `field` es obligatorio.

### Ejemplos comunes

```php
// Campo ID (solo lectura)
'id' => ['field' => 'id', 'saved' => false],

// Campo con JOIN (tabla aliasada)
'status_name' => ['field' => 's.name', 'saved' => false],

// Campo sensible (contraseña: no se lista, no filtra, se omite si vacío en update)
'password' => ['field' => 'u.password', 'filter' => false, 'listed' => false, 'optional' => true],

// Campo con valor por defecto
'status_id' => ['field' => 'u.status_id', 'default' => 1],

// Campo restringido por rol
'internal_notes' => ['field' => 'internal_notes', 'roles' => [1, 2]],
```

---

## Definición de consultas — `$get_params`

Configura la consulta base para todas las operaciones de lectura.

```php
protected $get_params = [
    'table'   => 'nombre_tabla',   // Nombre de la tabla principal (sin prefijo)
    'filters' => [],               // Filtros fijos definidos en el controlador
    'joins'   => [],               // JOINs a otras tablas
    'search'  => [],               // Columnas en las que aplica ?search=
];
```

### Tabla con alias

```php
'table' => 'users u',
```

### JOINs

Cada JOIN es un array con `table` y `match`. El tercer elemento de `match` define el tipo (opcional, por defecto `LEFT`):

```php
'joins' => [
    [
        'table' => 'user_roles r',
        'match' => ['r.id', 'u.role_id'],           // LEFT JOIN por defecto
    ],
    [
        'table' => 'user_status s',
        'match' => ['s.id', 'u.status_id', 'INNER'], // INNER JOIN
    ],
],
```

Tipos soportados: `LEFT`, `RIGHT`, `INNER`.

### Filtros fijos en el controlador

Se pueden agregar filtros programáticos que no provienen de la URL:

```php
// En __construct() o en el método correspondiente:
$this->get_params['filters']['id'] = ['u.id', USER_ID, '='];
```

Formato de cada filtro: `[columna, valor, operador]`.

### Campos de búsqueda

Define sobre qué columnas actúa el parámetro `?search=` (búsqueda LIKE + CONCAT):

```php
'search' => ['u.first_name', 'u.last_name', 'u.email'],
```

---

## Reglas de validación — `$rules`

Define las reglas que se aplican a los campos en POST y PUT. El formato es una cadena de reglas separadas por `|`:

```php
protected $rules = [
    'email'    => 'required|email|unique:users:email',
    'name'     => 'required|min:3|max:100',
    'role_id'  => 'numeric|exist:user_roles:id',
    'password' => 'min:8',
];
```

### Reglas disponibles

| Regla | Parámetro | Descripción |
|-------|-----------|-------------|
| `required` | — | El campo no puede estar vacío |
| `min:N` | N = mínimo de caracteres | Longitud mínima |
| `max:N` | N = máximo de caracteres | Longitud máxima |
| `min_value:N` | N = valor mínimo | Valor numérico mínimo |
| `max_value:N` | N = valor máximo | Valor numérico máximo |
| `email` | — | Formato de correo electrónico |
| `numeric` | — | Debe ser numérico |
| `alpha` | — | Solo letras (incluye acentos y ñ) |
| `alpha_num` | — | Solo letras y números |
| `alpha_dash` | — | Letras, números, guiones y guiones bajos |
| `alpha_spaces` | — | Letras y espacios |
| `base64` | — | Cadena base64 válida |
| `date_format` | — | Formato de fecha `YYYY-MM-DD` |
| `time_format` | — | Formato de hora `HH:MM:SS` |
| `decimal` | — | Número decimal (solo dígitos y punto) |
| `rfc` | — | RFC mexicano (persona física o moral) |
| `url` | — | URL válida |
| `boolean` | — | Valor booleano (`true` o `1`) |
| `json` | — | Cadena JSON válida |
| `unique:tabla:columna` | tabla y columna | El valor debe ser único en la DB; en UPDATE ignora el registro actual |
| `exist:tabla:columna` | tabla y columna | El valor debe existir como FK en la DB |

---

## Operaciones CRUD

### GET (index / show)

- `GET /api/v5/modulo` → `REQUEST_TYPE = index` → `index()` → `get()`
- `GET /api/v5/modulo/42` → `REQUEST_TYPE = show` → `show()` → `get()`

`BaseModel::get()` ejecuta `DBGet::Get()` con el modo `list` (múltiples resultados) o `NULL` (un solo registro según `?id`).

Responde con `ApiResponse::Set('SUCCESS', ['data' => ...])`.

Si no hay resultados, responde con el código `404000`.

### POST (store)

- `POST /api/v5/modulo` → `REQUEST_TYPE = store` → `store()` → `post()`

1. Llama a `init()` para preparar parámetros.
2. Construye `$fields` mediante `queryFields()` — lee `$_payload` y filtra según `$moduleFields` (`saved`, `optional`, `roles`).
3. Valida con `FieldsValidator::Validation()`.
4. Si hay `$this->queryFields`, se mezclan con los campos construidos.
5. Ejecuta `DBStore::Store($table, [$fields])`.
6. Responde con `ApiResponse::Set('CREATED', ['data' => ['inserted_id' => $id]])`.

### PUT (update)

- `PUT /api/v5/modulo/42` → `REQUEST_TYPE = update` → `update()` → `put()`

1. Construye `$fields` igual que en POST, pero respeta `optional = true` (omite campos vacíos).
2. Valida con `FieldsValidator::Validation($fields, $rules, $this->entryId)` — pasa el ID para que `unique` ignore el registro actual.
3. Ejecuta `DBUpdate::Update($table, $fields, $filters)`.
4. Responde con `UPDATED` o `NOCHANGE` según las filas afectadas.

### DELETE (destroy)

- `DELETE /api/v5/modulo/42` → `REQUEST_TYPE = destroy` → `destroy()` → `delete()`

1. Verifica que `?id` esté presente.
2. Si `$table_assoc` está definido, verifica que el registro no esté referenciado en otras tablas antes de borrar.
3. Ejecuta `DBDelete::delete($table, $filters, $table_assoc)`.
4. Responde con `DELETED` o `NOCHANGE`.

#### `$table_assoc` — verificación de FK antes de borrar

```php
$this->table_assoc = [
    [
        'table'  => 'facturas',
        'column' => 'proveedor_id',
        'value'  => $this->entryId,
    ],
];
```

Si la tabla referenciada tiene registros asociados, lanza `AppException` con código `902002` antes de ejecutar el DELETE.

---

## Capas internas de base de datos

### DBGet

**Namespace:** `App\Model\DBGet` | Archivo: `app/core/model/get.php`

Método principal:
```php
DBGet::Get(array $params, ?string $action = NULL): array|null
```

| `$action` | Comportamiento |
|-----------|----------------|
| `'list'`  | `SELECT ... LIMIT x, y` — devuelve array de arrays |
| `'count'` | `SELECT COUNT(*) results` — devuelve `['results' => N]` |
| `NULL`    | `SELECT ... LIMIT 1` — devuelve un solo array asociativo |

Estructura de `$params`:

```php
[
    'table'       => 'nombre_tabla',
    'fields'      => ['col1 alias1', 'col2 alias2'],  // SELECT fields
    'filters'     => [ ['columna', 'valor', 'OPERADOR'] ],
    'joins'       => [ ['table' => '...', 'match' => [...]] ],
    'order'       => [ ['campo', 'ASC|DESC'] ],
    'group_by'    => ['campo1', 'campo2'],
    'page'        => 1,
    'max_results' => 20,
]
```

#### Operadores de filtro interno

| Operador | SQL generado |
|----------|-------------|
| `=`, `!=`, `<`, `>`, `<=`, `>=` | Comparación directa |
| `LIKE` | `LIKE '%valor%'` |
| `IS_NULL` | `IS NULL` |
| `NOT_NULL` | `IS NOT NULL` |
| `IN` | `IN (v1, v2, ...)` — el valor es una cadena `'v1,v2,v3'` |
| `OR` | `(col1 = v1 \|\| col2 = v2)` — columna es array, valor es array |

Función especial con cuarto parámetro:
```php
// CONCAT: aplica CONCAT(COALESCE(col1,''), COALESCE(col2,'')) LIKE '%valor%'
['u.first_name, u.last_name', 'Juan', 'LIKE', 'CONCAT']
```

---

### DBStore

**Namespace:** `App\Model\DBStore` | Archivo: `app/core/model/store.php`

```php
DBStore::Store(string $table, array $fields): string|int
// Retorna el lastInsertId()
```

`$fields` es un array de registros (permite inserción múltiple). Cada registro es un array asociativo `['columna', 'valor']`:

```php
$fields[] = [
    'name'   => ['name', 'Contratación directa'],
    'slug'   => ['slug', 'contratacion-directa'],
    'active' => ['active', 1],
];
DBStore::Store('tipos_contrato', $fields);
```

Genera internamente:
```sql
INSERT INTO prefix_tipos_contrato (name, slug, active) VALUES (:name_0, :slug_0, :active_0)
```

---

### DBUpdate

**Namespace:** `App\Model\DBUpdate` | Archivo: `app/core/model/update.php`

```php
DBUpdate::Update(string $table, array $fields, array $filters, array $joins = []): int
// Retorna rowCount()
```

Cada campo en `$_fields`: `['columna', 'valor', 'modo']`

| Modo | SQL generado |
|------|-------------|
| Cualquier otro | `columna = :param` |
| `'increase'` | `columna = columna + :param` |
| `'decrease'` | `columna = columna - :param` |

Ejemplo:
```php
DBUpdate::Update('contratos', [
    ['total_amount', 1500000, '='],
    ['updated_at', date('Y-m-d'), '='],
], [
    ['id', 42, '='],
]);
```

---

### DBDelete

**Namespace:** `App\Model\DBDelete` | Archivo: `app/core/model/delete.php`

```php
DBDelete::delete(string $table, array $filter, ?array $table_assoc = NULL): int
// Retorna rowCount()
```

Operadores soportados en filtros: `=`, `!=`, `<>`, `>`, `>=`, `<`, `<=`, `LIKE`.

---

## Filtros desde la URL

`BaseModel::getParamsFilters()` traduce los query params de la URL a filtros internos del ORM, siempre que el campo tenga `filter = true` en `$moduleFields`.

### Sintaxis

```
?campo=operador:valor
?campo=valor          (equivale a eq:valor)
```

### Operadores disponibles

| URL op | SQL op |
|--------|--------|
| `eq`   | `=` |
| `lt`   | `<` |
| `gt`   | `>` |
| `lte`  | `<=` |
| `gte`  | `>=` |
| `ne`   | `!=` |
| `lk`   | `LIKE` |
| `isn`  | `IS_NULL` |
| `non`  | `NOT_NULL` |
| `in`   | `IN` |

### Ejemplos

```
GET /api/v5/contratos?status_id=eq:1
GET /api/v5/contratos?fiscal_year=2024
GET /api/v5/contratos?total_amount=gte:100000
GET /api/v5/contratos?provider_id=in:1,2,3
GET /api/v5/contratos?call_date=isn:
```

---

## Búsqueda de texto — `?search=`

Permite buscar texto libre en las columnas declaradas en `$get_params['search']`.

El sistema normaliza el texto de búsqueda antes de construir los filtros:
- Elimina acentos españoles (á→a, é→e, ñ→n, etc.)
- Convierte a minúsculas
- Elimina caracteres no alfanuméricos
- Ignora stopwords comunes en español: `la`, `el`, `de`, `y`, `en`, `que`, `un`, `una`, `por`, `con`, etc.
- Divide en tokens y genera un filtro `LIKE` con `CONCAT(COALESCE(...))` por cada token

```
GET /api/v5/users?search=Juan García
```

Genera internamente:
```sql
WHERE CONCAT(COALESCE(u.first_name,''), COALESCE(u.last_name,''), COALESCE(u.email,'')) LIKE '%juan%'
AND   CONCAT(COALESCE(u.first_name,''), ...) LIKE '%garcia%'
```

---

## Paginación

Se activa con `?embed=pagination`. Opcionalmente se combinan `?page=N` y `?limit=N`.

```
GET /api/v5/contratos?embed=pagination&page=2&limit=20
```

La respuesta incluye un bloque `pagination`:

```json
{
  "data": [...],
  "pagination": {
    "pages": 10,
    "results": 200,
    "current_page": 2
  }
}
```

Si hay `group_by` activo, el conteo se realiza contando los resultados de la lista agrupada en lugar de usar `COUNT(*)`.

---

## Ordenamiento y agrupación

### Ordenamiento — `?sort=`

Prefijo `+` = ASC, prefijo `-` = DESC. Se puede ordenar por múltiples campos separados por coma.

```
GET /api/v5/contratos?sort=-fiscal_year,+contract_id
GET /api/v5/contratos?sort=rand
```

Solo se permiten campos declarados en `$moduleFields`.

### Agrupación — `?groupby=`

```
GET /api/v5/contratos?groupby=fiscal_year
```

### Selección de campos — `?fields=`

Permite restringir los campos devueltos:

```
GET /api/v5/contratos?fields=id,contract_number,total_amount
```

Solo se devuelven campos con `listed = true` y que el rol del usuario tenga acceso.

---

## Validación de campos

**Clase:** `App\Validation\FieldsValidator`  
**Método:** `Validation(array $request, array $rules, ?int $id): array|false`

- Retorna `false` si no hay errores.
- Retorna un array asociativo con los errores por campo si los hay.

En `BaseModel::post()` y `BaseModel::put()`:
```php
$fv = new FieldsValidator();
$validation = $fv->Validation($fields, $this->rules, $this->entryId);
if ($validation) {
    ApiResponse::Set(400000, ['errors' => $validation]);
}
```

El `$id` se utiliza en la regla `unique` para ignorar el propio registro al actualizar.

---

## Opciones avanzadas del controlador

### `$methodOptions['end']`

Por defecto es `true`. Cuando está en `true`, cada operación CRUD llama automáticamente a `ApiResponse::Set()` al finalizar, terminando la ejecución.

Si se necesita componer una respuesta manualmente (por ejemplo, para encadenar operaciones), se establece en `false`:

```php
$this->methodOptions['end'] = false;
$insertedId = $this->traitstore(); // No llama ApiResponse::Set()
// ... lógica adicional ...
ApiResponse::Set('CREATED', ['data' => $result]);
```

### `$queryFields`

Permite inyectar campos calculados o derivados que no vienen del payload directamente (ya sea en `store` o `update`):

```php
$this->queryFields = [
    'password' => ['u.password', password_hash($plaintext, PASSWORD_DEFAULT)],
];
```

Estos campos se mezclan con los generados por `queryFields()` antes de ejecutar el INSERT/UPDATE.

---

## Ejemplo completo de módulo

### `app/api/materias/controller.php`

```php
<?php
use App\Model\BaseModel;
use App\Model\Crud;

class MateriaComponent extends BaseModel {

    use Crud;

    protected $moduleFields = [
        'id'   => ['field' => 'id', 'saved' => false],
        'name' => ['field' => 'name'],
        'slug' => ['field' => 'slug'],
    ];

    protected $get_params = [
        'table'   => 'c_materia',
        'filters' => [],
        'joins'   => [],
        'search'  => [],
    ];

    protected $rules = [
        'name' => 'required|max:50',
        'slug' => 'required|max:50|unique:c_materia:slug',
    ];

    public function __construct() {
        global $_payload;
        $extra = [];
        if ($_payload && isset($_payload->name)) {
            $extra['slug'] = toAlphanumeric($_payload->name, '-');
        }
        parent::__construct($extra);
    }
}
?>
```

### `app/api/materias/index.php`

```php
<?php
use App\Auth\ModuleHandler;

require_once __DIR__ . '/controller.php';
$materia = new MateriaComponent();

$accepted_methods = [
    'index'   => [false],           // Público
    'show'    => [false],           // Público
    'store'   => [true, [1, 2, 3]], // Requiere auth, roles 1, 2 o 3
    'update'  => [true, [1, 2, 3]],
    'destroy' => [true, [1, 2, 3]],
];
ModuleHandler::Validate($accepted_methods, $materia);
?>
```

### Peticiones resultantes

```
GET    /api/v5/materias          → index()  → SELECT * FROM c_materia
GET    /api/v5/materias/5        → show()   → SELECT * FROM c_materia WHERE id=5 LIMIT 1
POST   /api/v5/materias          → store()  → INSERT INTO c_materia (name, slug) VALUES (...)
PUT    /api/v5/materias/5        → update() → UPDATE c_materia SET name=..., slug=... WHERE id=5
DELETE /api/v5/materias/5        → destroy()→ DELETE FROM c_materia WHERE id=5
```

---

## Resumen del flujo completo (POST)

```
POST /api/v5/materias
  Body: {"name": "Obra Pública"}

1. midelware.php       → define REQUEST_TYPE = 'store'
2. modules.php         → cleanData() → $_payload->name = "Obra Pública"
3. materias/index.php  → new MateriaComponent()
                         → constructor agrega slug = "obra-publica" al payload
4. ModuleHandler::Validate()
                         → verifica JWT y rol del usuario
                         → llama $materia->store()
5. Crud::store()       → BaseModel::post()
6. BaseModel::post()
   a. init()           → construye parámetros de tabla, joins, filtros
   b. queryFields()    → lee payload: {name: "Obra Pública", slug: "obra-publica"}
   c. FieldsValidator  → valida: required ✓, max:50 ✓, unique ✓
   d. DBStore::Store() → INSERT INTO c_materia (name, slug) VALUES (?, ?)
   e. ApiResponse::Set('CREATED', {inserted_id: 7})
```
