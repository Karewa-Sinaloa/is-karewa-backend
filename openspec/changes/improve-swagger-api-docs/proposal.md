# Proposal

## Why

El contrato OpenAPI publicado (`api/docs/openapi.json`) está desfasado respecto al código actual: documenta campos que ya no existen (`config.data`/`public`), endpoints que los módulos no aceptan, omite endpoints existentes, marca seguridad incorrecta en operaciones y no recoge códigos de respuesta nuevos (p. ej. `902003`, `429000`). Sin una fuente verificable, la documentación pública pierde confianza y cualquier refactor vuelve a desfasarla sin que nada lo detecte.

## What Changes

- Se regenera y corrige la especificación para que refleje el estado real de los módulos: campos (`value` en `config`), inventario de operaciones y anotaciones de seguridad derivadas de las declaraciones de cada módulo.
- Se eliminan operaciones fantasma (`GET /attachments`, `GET /frontend-logs`) y se añaden las faltantes (`POST /hcaptcha`).
- Se alinean las respuestas de error con el catálogo `api_codes.yml` (incluye `902003 APP_DATABASE_QUERY_FAILED` y `429000 APP_RATE_LIMIT_EXCEEDED`).
- Se corrige la semántica de listados: colecciones vacías responden `200` con `data: []`; el `404` queda solo para ITEM no encontrado.
- Se mejora la calidad del contrato: schemas del envelope en respuestas exitosas, `operationId` estable por operación y respuestas de error definidas una sola vez en `components/responses` e referenciadas con `$ref` (reduce el documento de ~778 KB y lo hace mantenible).
- Se añade verificación automatizada de sincronización entre controllers y JSON publicado.
- **BREAKING**: no aplica cambios de comportamiento en la API; el cambio afecta solo al contrato documentado (la corrección de seguridad/errores en la spec es un cambio de documentación, no de runtime).

## Capabilities

### New Capabilities

- `swagger-scalar-api-docs`: contrato OpenAPI público y UI Scalar; en este cambio se agregan requisitos de exactitud (campos, inventario de operaciones, seguridad por operación, códigos de error, semántica de listados) y de calidad verificable (schemas de envelope, respuestas compartidas, detección de desfase).

### Modified Capabilities

- Ninguno.

## Impact

- `tools/generate-openapi.php` (lector de controllers/`index.php`/`api_codes.yml` y emisor del JSON).
- `api/docs/openapi.json` y su copia publicada en `httpdocs/api/docs/openapi.json`.
- Nueva test de sincronización bajo `tests/` (convención `openspec/specs/testing`).
- Relacionado con cambios abiertos: `add-swagger-scalar-api-docs` (introduce esta capacidad), `harden-api-security` (código `429000`), `align-users-field-mapping` y los cambios ya archivados de `config-module-fields`/`core-module-crud` cuyo comportamiento la spec debe reflejar.
- Consumidores de la documentación (frontend, Postman, integradores): ven un contrato más preciso; los `operationId` nuevos y las respuestas por `$ref` son adiciones compatibles con OpenAPI 3.0.3.
