# API Response — Validación y Manejo de Errores

Este documento describe cómo el API genera respuestas, valida datos y maneja errores a lo largo del ciclo de vida de una petición.

---

## Cómo funciona el sistema de respuestas

Todas las respuestas del API pasan por la clase `ApiResponse::Set()`, ubicada en `app/core/helpers/api_response.php`. Este método es el **único punto de salida** de cualquier respuesta, ya sea exitosa o de error.

```php
ApiResponse::Set(string $code, array $data = null);
```

- Lee `app/core/config/api_codes.yml` y busca el código indicado.
- Agrega los datos adicionales (`data`, `errors`, etc.) al cuerpo de la respuesta.
- Incluye automáticamente un campo `meta` con el `session_id` de la petición.
- Establece el HTTP status code correspondiente.
- Serializa la respuesta como JSON y **termina la ejecución** (`die()`).

### Estructura de una respuesta

```json
{
  "message": "Descripción del resultado",
  "code": "CÓDIGO_INTERNO",
  "http_code": 200,
  "data": { },
  "meta": {
    "session_id": "uuid-de-la-peticion"
  }
}
```

> Los campos `code`, `http_code`, `message` y `meta` son fijos y no pueden ser sobreescritos al pasar datos adicionales.

---

## Flujo de validación de una petición

Cada petición pasa por las siguientes capas antes de llegar al controlador:

```
1. midelware.php      → Verifica que el método HTTP sea válido (GET, POST, PUT, DELETE)
                        y determina el REQUEST_TYPE (index, show, store, update, destroy)
                        Si el método no es válido → ApiResponse::Set(900000)

2. modules.php        → Resuelve el módulo solicitado (?m=nombre)
                        Si no existe → ApiResponse::Set(900001)

3. post_params.php    → En POST y PUT, decodifica el JSON del body (php://input)
                        Si el body está vacío o malformado → ApiResponse::Set(900002)

4. index.php          → Define qué métodos requieren autenticación y qué roles los pueden usar
   ModuleHandler      → Valida el JWT del header Authorization
                        Sin token cuando se requiere → ApiResponse::Set(901004)
                        Token inválido o expirado    → ApiResponse::Set(901001..901003)
                        Rol sin permiso              → ApiResponse::Set(901004)

5. BaseModel          → Valida los campos del payload con FieldsValidator
                        Errores de validación → ApiResponse::Set(400000, ['errors' => [...]])
                        Sin ID en update/delete → ApiResponse::Set(400002)

6. DBGet/DBStore/...  → Ejecuta la query MySQL
                        Error de base de datos → ApiResponse::Set(902000)
                        Relación FK violada    → ApiResponse::Set(902002)

7. Respuesta exitosa  → ApiResponse::Set('SUCCESS' | 'CREATED' | 'UPDATED' | 'DELETED' | 'NOCHANGE')
```

---

## Validación de campos (`FieldsValidator`)

Ubicación: `app/core/validation/fields.php`

Cada controlador define un array `$rules` con las reglas por campo:

```php
protected $rules = [
    'name'  => 'required|max:100',
    'email' => 'required|email|unique:usuarios:email',
    'age'   => 'numeric|min_value:18|max_value:99',
];
```

Las reglas se separan con `|`. Si hay errores, se retorna un array asociativo; si todo es válido, retorna `false`.

### Reglas disponibles

| Regla | Sintaxis | Descripción |
|-------|----------|-------------|
| `required` | `required` | El campo no puede estar vacío |
| `max` | `max:N` | Longitud máxima de N caracteres |
| `min` | `min:N` | Longitud mínima de N caracteres |
| `max_value` | `max_value:N` | Valor numérico máximo de N |
| `min_value` | `min_value:N` | Valor numérico mínimo de N |
| `numeric` | `numeric` | Solo números |
| `email` | `email` | Formato de correo electrónico válido |
| `alpha` | `alpha` | Solo letras (incluye acentos y ñ) |
| `alpha_num` | `alpha_num` | Solo letras y números (sin espacios) |
| `alpha_dash` | `alpha_dash` | Letras, números, guiones y guiones bajos |
| `alpha_spaces` | `alpha_spaces` | Letras y espacios |
| `base64` | `base64` | Formato Base64 válido |
| `decimal` | `decimal` | Número decimal (`0.00`) |
| `boolean` | `boolean` | Valor booleano (`true` o `1`) |
| `json` | `json` | String JSON válido |
| `url` | `url` | URL válida |
| `rfc` | `rfc` | Formato RFC mexicano |
| `date_format` | `date_format` | Fecha en formato `YYYY-MM-DD` |
| `time_format` | `time_format` | Hora en formato `HH:MM:SS` |
| `unique` | `unique:tabla:columna` | El valor no debe existir ya en esa columna de la tabla |
| `exist` | `exist:tabla:columna` | El valor debe existir en esa columna de la tabla |

### Ejemplo de respuesta con errores de validación

```json
{
  "message": "Error validating payload data",
  "code": "APP_PAYLOAD_VALIDATION",
  "http_code": 400,
  "errors": {
    "name": {
      "required": "Field required"
    },
    "email": {
      "email": "Invalid email format",
      "unique": "Column value not unique"
    }
  },
  "meta": {
    "session_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

---

## Autenticación JWT

El token se envía en el header `Authorization`:

```
Authorization: Bearer eyJhbGciOiJSUzI1NiJ9...
```

Al validar exitosamente, el sistema define las siguientes constantes disponibles en toda la petición:

| Constante | Contenido |
|-----------|-----------|
| `USER_ID` | ID del usuario autenticado |
| `USER_ROLE` | ID del rol del usuario |
| `USER_NAME` | Nombre del usuario |
| `USER_LASTNAME` | Apellido del usuario |
| `USER_EMAIL` | Correo del usuario |
| `EXPIRATION` | Timestamp de expiración del token |
| `AUTHENTICATED` | `true` si está autenticado, `false` si no |

### Roles

Los roles se definen como enteros en el array `$accepted_methods` del `index.php` de cada módulo. Si el rol del usuario no está en la lista, la petición es rechazada con `901004`.

---

## Manejo de excepciones internas

Las excepciones internas usan la clase `AppException` (en `app/core/helpers/custom_exceptions.php`), que extiende `Exception` de PHP. El código del error pasado al constructor corresponde a un código numérico de `api_codes.yml`.

```php
throw new \AppException('Mensaje descriptivo para el log', 902000);
```

Al capturarse, se convierte en respuesta API:

```php
catch (\AppException $e) {
    ApiResponse::Set($e->errorCode()); // pasa el código numérico
}
```

Al destruirse, `AppException` **escribe automáticamente en el log de errores** el mensaje, la línea, el archivo y el código.

---

## Registro de errores (Logs)

Función global: `error_logs(array $data, string $file = DEBUG_LOG_FILE)`

Ubicación de los archivos de log (configurables en `app/config.yml`):

| Archivo | Uso |
|---------|-----|
| `logs/error.log` | Errores del API y excepciones |
| `logs/debug.log` | Información de depuración |
| `logs/frontend.log` | Errores reportados desde el frontend |
| `logs/payment.log` | Eventos de pagos |

Cada línea de log tiene el formato:

```
[DD-MM-YYYY HH:MM:SS] - [IP] - [USER_ID o session_id] - Mensaje del error
```

---

## Catálogo de códigos de respuesta

### ✅ Respuestas exitosas

| Clave | HTTP | Código interno | Descripción |
|-------|------|----------------|-------------|
| `SUCCESS` | 200 | `SUCCESS` | Consulta exitosa (GET) |
| `UPDATED` | 200 | `APP_UPDATE_SUCCESS` | Registro actualizado |
| `CREATED` | 201 | `APP_CREATE_SUCCESS` | Registro creado |
| `NOCHANGE` | 202 | `NOCHANGE` | La operación no realizó cambios |
| `DELETED` | 202 | `DELETED` | Registro eliminado |

---

### ❌ Errores de petición y módulos (9000xx)

| Código | HTTP | Código interno | Descripción | Causa común |
|--------|------|----------------|-------------|-------------|
| `900000` | 405 | `APP_INVALID_REQUEST_METHOD` | Método HTTP no permitido | Usar PATCH o HEAD en lugar de GET/POST/PUT/DELETE |
| `900001` | 404 | `APP_MODULE_NOT_FOUND` | Módulo no encontrado | El parámetro `?m=` apunta a un módulo inexistente |
| `900002` | 500 | `API_ERROR_RETRIEVING_PAYLOAD` | Error al leer el body de la petición | JSON malformado en el cuerpo del POST o PUT |
| `909000` | 500 | `APP_UNKNOWN_INTERNAL_ERROR` | Error interno desconocido | Fallo no controlado en el servidor |

---

### 🔐 Errores de autenticación (901xx)

| Código | HTTP | Código interno | Descripción | Causa común |
|--------|------|----------------|-------------|-------------|
| `901000` | 500 | `APP_AUTH_INTERNAL_ERROR` | Error interno al procesar el token JWT | Claves JWT corruptas o error al codificar/decodificar |
| `901001` | 403 | `APP_AUTH_SESSION_EXPIRED` | Sesión expirada | El token JWT venció (`exp` superado) |
| `901002` | 401 | `APP_AUTH_UNEXPECTED_VALUE` | Valor inesperado en el token | Token con estructura inválida o campos faltantes |
| `901003` | 401 | `APP_AUTH_IVALID_SIGNATURE` | Firma del token inválida | Token modificado o firmado con clave incorrecta |
| `901004` | 401 | `APP_AUTH_FAILED` | Autenticación fallida | Sin token, rol no permitido o credenciales incorrectas en login |
| `901005` | 403 | `APP_AUTH_INVALID_CODE` | Código de recuperación inválido | El código enviado no coincide con el registrado |
| `901006` | 403 | `APP_AUTH_EXPIRED_CODE` | Código de validación expirado | El código de recuperación o verificación venció |
| `901007` | 500 | `APP_AUTH_NO_KEYS` | Archivos de claves JWT no encontrados | Faltan `jwtRS256.key` o `jwtRS256.key.pub` en `app/.keys/` |

---

### 📋 Errores de validación de datos (400xxx)

| Código | HTTP | Código interno | Descripción | Causa común |
|--------|------|----------------|-------------|-------------|
| `400000` | 400 | `APP_PAYLOAD_VALIDATION` | Error al validar los campos del body | Campos requeridos vacíos, formatos inválidos, valores duplicados |
| `400001` | 406 | `API_NO_PAYLOAD_RECEIVED` | No se recibió body en la petición | POST o PUT enviado sin cuerpo JSON |
| `400002` | 406 | `API_NO_ENTRY_ID_PROVIDED` | No se proporcionó un ID | UPDATE o DELETE sin el parámetro `?id=` en la URL |
| `404000` | 404 | `APP_RESULTS_NOT_FOUND` | No se encontraron resultados | La consulta no devolvió registros |

---

### 🗄️ Errores de base de datos (902xx)

| Código | HTTP | Código interno | Descripción | Causa común |
|--------|------|----------------|-------------|-------------|
| `902000` | 500 | `APP_DATABASE_INTERNAL_ERROR` | Error al procesar la query MySQL | Query inválida, conexión perdida o error de sintaxis SQL |
| `902001` | 500 | `APP_DATABASE_UPDATE_NO_FIELDS` | No hay campos para actualizar | PUT enviado sin campos modificables en el payload |
| `902002` | 409 | `APP_ENTRY_RELATIONSHIP` | El registro está relacionado con otro | Intentar eliminar un registro que es FK de otra tabla |

---

### 📧 Errores de correo (903xx)

| Código | HTTP | Código interno | Descripción | Causa común |
|--------|------|----------------|-------------|-------------|
| `903000` | 500 | `APP_MAILER_INTERNAL_ERROR` | Error al enviar el correo | Configuración SMTP incorrecta o servidor de correo no disponible |

---

### 👤 Errores de registro de usuarios (904xx)

| Código | HTTP | Código interno | Descripción | Causa común |
|--------|------|----------------|-------------|-------------|
| `904000` | 500 | `APP_REGISTRATION_FAILED` | Error al registrar el usuario | Fallo al insertar el usuario en la base de datos |

---

### 🔌 Errores de servicios externos (905xx)

| Código | HTTP | Código interno | Descripción | Causa común |
|--------|------|----------------|-------------|-------------|
| `905000` | 500 | `APP_THIRD_PARTY_ERROR` | Error en servicio de terceros | Fallo en una API externa (hCaptcha, pasarela de pago, etc.) |

---

### 📁 Errores de carga de archivos (906xx)

| Código | HTTP | Código interno | Descripción | Causa común |
|--------|------|----------------|-------------|-------------|
| `906000` | 413 | `APP_UPLOAD_MAX_SIZE` | El archivo supera el tamaño máximo | Archivo más grande que el límite configurado |
| `906001` | 500 | `APP_UPLOAD_NOT_COMPLETED` | No se pudo subir el archivo | Error al mover el archivo al servidor |
| `906002` | 415 | `APP_UPLOAD_INVALID_FORMAT` | Formato de archivo no permitido | Extensión o MIME type no aceptado |
| `906003` | 500 | `APP_FOLDER_NOT_CREATED` | No se pudo crear el directorio | Permisos insuficientes en el servidor |

---

## Agregar un nuevo código de error

1. Abre `app/core/config/api_codes.yml`.
2. Agrega una nueva entrada siguiendo el esquema:
   ```yaml
   907000:
     message: Descripción del error
     code: APP_NOMBRE_DEL_ERROR
     http_code: 500
   ```
3. Úsalo en el código con `ApiResponse::Set(907000)` o lánzalo desde una excepción con `throw new \AppException('Mensaje', 907000)`.

> **Convención de rangos:** `900xxx` sistema/routing · `901xxx` autenticación · `902xxx` base de datos · `903xxx` correo · `904xxx` usuarios · `905xxx` terceros · `906xxx` archivos.
