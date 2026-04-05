# Changelog

Todos los cambios relevantes de este proyecto se documentan en este archivo.

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
