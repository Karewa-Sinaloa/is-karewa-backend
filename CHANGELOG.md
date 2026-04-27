# Changelog

Todos los cambios relevantes de este proyecto se documentan en este archivo.

---

## [Unreleased] — 2026-04-26 · rama `development`

### Añadido
- `tests/JWTKeyEncodeTest.php` — Se agrega una prueba dedicada para validar la estructura del payload generado por `jwtToken::encode()`, cubriendo expiración, mensaje y datos básicos del usuario.

### Modificado
- `app/core/auth/jwt_token.php` — Se tipan de forma explícita los métodos de manejo JWT, se corrige la captura de `UnexpectedValueException` y `encode()` ahora expone el payload crudo junto con el token para facilitar validaciones automatizadas.
- `phpunit.xml`, `tests/RoutesTest.php` — Se corrigen las constantes globales usadas por la suite, se agregan parámetros de JWT para pruebas y se actualizan los requires para depender de `CORE_PATH`.
- `app/core/config/base.php`, `app/core/helpers/custom_exceptions.php` — Se endurece la carga de configuración inicializando `$_config` como objeto vacío y se evita que `AppException` falle al registrar errores cuando `MODULE` todavía no está definido.

---

## [Unreleased] — 2026-04-18 · rama `development`

### Modificado
- `app/core/bootstrap/routes.php`, `app/core/bootstrap/init.php`, `app/core/bootstrap/methods.php` — Se limpia la integración del nuevo resolutor de rutas, se carga antes la validación de sesión/método y `REQUEST_TYPE` se calcula con retorno explícito, descartando peticiones `OPTIONS` antes de validar el resto del flujo.
- `app/core/helpers/api_response.php`, `app/core/auth/session.set.php`, `app/core/bootstrap/midelware.php`, `app/core/helpers/custom_exceptions.php` — Se agregan tipos de retorno y firmas más estrictas en autenticación, respuestas y excepciones para estabilizar contratos internos en PHP 8.4.
- `phpunit.xml`, `tests/RoutesTest.php`, `tests/ValidationTest.php` — Se centraliza en PHPUnit el bootstrap, constantes globales y salidas de reportes, eliminando configuración duplicada dentro de las pruebas.
- `app/api/proveedores/controller.php` — El módulo de proveedores expone `slug` en lugar de `shortname` para alinearse con el campo persistido por el catálogo.

---

## [Unreleased] — 2026-04-18 · rama `master`

### Añadido
- `app/core/bootstrap/routes.php` — Se centraliza el catálogo de rutas válidas del API en un archivo dedicado para resolver módulos permitidos antes de cargar su controlador.
- `phpunit.xml`, `tests/RoutesTest.php` — Se agrega configuración de PHPUnit en la raíz del repositorio y una prueba para validar la resolución de rutas mediante `moduleManager`.

### Modificado
- `composer.json`, `composer.lock`, `composer.phar` — Se reubican los artefactos de Composer a la raíz del repositorio para unificar dependencias y autoload fuera de `app/core/third_party/`.
- `app/core/bootstrap/init.php` — Se actualiza la carga de `vendor/autoload.php`, se integra `routes.php` al bootstrap y se ajusta el orden de inicialización de métodos, payload y módulos.
- `app/core/bootstrap/methods.php` — Se toma el mapeo de métodos desde `$_config->valid_requests`, se endurece la validación del método HTTP y se simplifica la detección de peticiones con `id`.
- `app/core/bootstrap/modules.php` — Se reemplaza la resolución directa del módulo por la clase `moduleManager`, validando rutas permitidas y buscando módulos tanto en `app/api/` como en `app/core/modules/`.
- `app/core/bootstrap/post_params.php` — Se sustituye `cleanData()` por `payload_decode()` y la decodificación del body solo se ejecuta en operaciones `store` y `update`.
- `app/core/helpers/api_response.php` — Se normaliza la construcción de respuestas con arreglos asociativos y opciones opcionales de metadatos para evitar inconsistencias al mezclar datos adicionales.
- `tests/ValidationTest.php`, `.gitignore` — Se actualiza la suite de pruebas para usar el autoload de la raíz y se ignoran `vendor/` y `.phpunit.result.cache` generados localmente.

---

## [Unreleased] — 2026-04-17 · rama `development`

### Modificado
- `app/api/contracts/controller.php` — Se califican los campos del módulo con el alias `c`, se corrige el catálogo relacionado en `partida_type`, se renombra `amount_exceeded` a `exceeded_amount` y se agregan campos de solo lectura para exponer nombres descriptivos de proveedor, unidades administrativas, tipo de contrato, periodo, procedimiento, materia, tipo de partida y estatus en las consultas.
- `app/core/bootstrap/midelware.php` — Se corrige el cálculo de paginación cuando no existe `group_by`, inicializando el contador antes de evaluar embebidos y evitando el acceso a índices inexistentes.
- `app/core/config/base.php` — Se agrega una validación explícita cuando la configuración no se carga como objeto y se definen valores por defecto seguros para `MYSQL_HOST`, `MYSQL_PORT`, `MYSQL_PREFIX`, `MYSQL_CHARSET` y `MYSQL_COLLATION`.
- `app/core/model/get.php`, `app/core/model/update.php`, `app/core/model/delete.php` — Se aplica `MYSQL_PREFIX` a joins, updates y verificaciones de asociaciones para mantener compatibilidad con tablas prefijadas en operaciones de lectura, actualización y borrado.

---

## [Unreleased] — 2026-04-15 · rama `master`

### Añadido
- `app/api/periodos-contratos/` — Nuevo módulo para el catálogo de periodos de contrato (`c_periods`), con soporte completo CRUD y generación automática de slug.

### Modificado
- `app/api/contracts/controller.php` — Ajustes al módulo de contratos para alinear campos, joins y validaciones con el esquema actual: `reported_period` cambia a `period_id` con relación a `c_periods`, `partida_type_id` cambia a `partida_type`, `contract_updated_at` reemplaza `updated_at` como campo de negocio, se renombran respaldos a `contract_backup` y `announcement_backup`, y se exponen `created_at`/`updated_at` como campos de solo lectura fuera de persistencia.
- `app/api/contracts/index.php` — Corrección de la variable enviada a `ModuleHandler::Validate()`: se usa la instancia real `$contracts` en lugar de la variable inexistente `$c_contracts`.

---

## [Unreleased] — 2026-04-08 · rama `master`

### Añadido
- `orm_wiki.md` — Documentación completa del ORM personalizado del sistema: arquitectura, capas de base de datos (`DB`, `DBGet`, `DBStore`, `DBUpdate`, `DBDelete`), `BaseModel`, trait `Crud`, definición de `$moduleFields`, `$get_params`, `$rules`, operaciones CRUD, filtros URL, búsqueda de texto, paginación, ordenamiento, validación y ejemplo completo de módulo.
- `app/api/contracts/` — Nuevo módulo para la gestión de contratos (`contratos`), con soporte completo CRUD.
- `app/api/estatus-contrato/` — Nuevo módulo para el catálogo de estatus de contrato (`c_estatus`), con soporte completo CRUD y generación automática de slug.
- `app/api/partidas/` — Nuevo módulo para el catálogo de partidas presupuestales (`c_partidas`), con soporte completo CRUD y generación automática de slug.
- `app/api/tipo-contrato/` — Nuevo módulo para el catálogo de tipos de contrato (`c_tipo`), con soporte completo CRUD y generación automática de slug.
- `app/api/unit-types/` — Nuevo módulo para el catálogo de tipos de unidad administrativa (`unit_types`), con soporte completo CRUD y generación automática de slug.

### Modificado
- `app/core/config/base.php` — Corrección en la validación CORS: se agrega el bloque `else` faltante que responde con error JSON (`CORS policy: This origin is not allowed`) cuando el origen de la petición no está en la lista de dominios permitidos; anteriormente la validación fallaba silenciosamente.

---

## [Unreleased] — 2026-04-05 · rama `master`

### Añadido
- `.github/copilot-instructions.md` — Instrucciones de contexto para sesiones de GitHub Copilot: arquitectura, flujo de peticiones, convenciones del proyecto, comandos de build y pruebas.
- `api_response.md` — Documentación completa del sistema de respuestas del API: flujo de validación, reglas de campos, autenticación JWT, manejo de excepciones, logs y catálogo de todos los códigos de error.
- `app/api/materias/` — Nuevo módulo para la gestión del catálogo de materias (`c_materia`), con soporte completo CRUD y generación automática de slug.

### Modificado
- `app/core/third_party/composer.json` — Actualización de `firebase/php-jwt` de `^6.0` a `^7.0` para corregir la vulnerabilidad de seguridad **CVE-2025-45769** (cifrado débil en versiones anteriores a 7.0.0).
- `app/core/third_party/composer.lock` — Actualización de dependencias:
  - `firebase/php-jwt` v6.11.1 → **v7.0.5** *(fix CVE-2025-45769)*
  - `brick/math` 0.14.1 → 0.14.8
  - `phpunit/phpunit` 12.5.5 → 12.5.16
  - `phpunit/php-code-coverage` 12.5.2 → 12.5.3
  - `phpunit/php-file-iterator` 6.0.0 → 6.0.1
  - `sebastian/comparator` 7.1.3 → 7.1.4
  - `sebastian/environment` 8.0.3 → 8.0.4
- `app/core/bootstrap/midelware.php` — Compatibilidad con PHP 8.4: tipado `Object` reemplazado por `object` (minúscula); inicialización explícita de `$this->payload` como `stdClass` antes del constructor.
- `app/core/bootstrap/post_params.php` — Inicialización de `$_payload` como `stdClass` vacío antes de procesar el body, evitando errores cuando la petición no trae payload; mejoras de indentación.
- `app/core/modules/access/local_login.php` — Correcciones de indentación en bloques de validación y manejo de base de datos.
- `app/api/procedimientos/controller.php` — Corrección en regla de validación `unique`: tabla incorrecta `admin_units:id` reemplazada por `c_procedures:id`.

---

## [2026-02-12] · rama `master`

### Modificado
- Avances en sección de configuración de procedimientos.

## [2026-02-10 / 2026-02-11] · rama `master`

### Modificado
- Avances en sección de unidades administrativas.
- Sección de proveedores completada.

## [Anteriores]

Consulta el historial de commits con:
```bash
git log --oneline
```
