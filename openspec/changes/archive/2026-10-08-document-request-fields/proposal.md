# Proposal

## Why

El contrato OpenAPI publicado no dice qué campos se deben enviar ni cuáles son opcionales en `POST`/`PUT`: los cuerpos de solicitud incluyen columnas que la API nunca escribe (`id`, `created_at`, campos de joins `*_name`, `*_backup`), no declaran `required`, y sus descripciones son genéricas (`Public field`). En `GET`, los parámetros `fields`, `sort`, `groupby` y `embed` no enumeran los valores que el backend acepta, así que un integrador debe leer el código para armar una petición correcta.

## What Changes

- El generador `tools/generate-openapi.php` parsea `$rules` de cada módulo y lo usa para documentar el contrato.
- Los cuerpos de `POST`/`PUT` solo incluyen campos enviables (presentes en `$moduleFields`, `saved=true` y no `readonly`).
- `POST` declara `required` con los campos enviables cuya regla contiene `required`; `PUT` no declara `required` y documenta la actualización parcial (solo campos enviados con valor no vacío).
- Cada propiedad documenta tipo, formato y restricciones derivadas de las reglas (`maxLength`, `minLength`, `minimum`/`maximum`, `email`, `uri`, `date`, `exist`, `unique`) además de notas de campo interno/no filtrable/restringido por rol.
- Los parámetros de consulta de `GET` enumeran los campos permitidos en `fields`, `sort` y `groupby`, restringen `embed` a `pagination`, detallan los campos de búsqueda reales del módulo y describen cada filtro con su tipo y sus reglas.
- Se corrige `properties` vacío de los endpoints sin campos para que sea un objeto JSON (`{}`) y no un arreglo.
- No hay cambios de comportamiento en la API; el alcance es documental.

## Capabilities

### New Capabilities

- Ninguna.

### Modified Capabilities

- `swagger-scalar-api-docs`: se agregan requisitos sobre campos de solicitud documentados (solo campos enviables, `required` derivado de `$rules`, tipos/restricciones por campo) y sobre la enumeración de valores permitidos en los parámetros de consulta.

## Impact

- `tools/generate-openapi.php` (parser de `$rules`, esquema de solicitud y parámetros de consulta).
- `api/docs/openapi.json` y `httpdocs/api/docs/openapi.json` (regenerados).
- `tests/OpenApiSyncTest.php` sigue garantizando que el documento publicado sea idéntico a la salida del generador.
- Consumidores del contrato: los esquemas dejan de aceptar campos no enviables (antes documentados incorrectamente como enviables).
