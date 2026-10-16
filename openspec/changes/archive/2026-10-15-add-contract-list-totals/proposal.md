# Proposal

## Why

El módulo de contratos devuelve únicamente el listado paginado de contratos, sin agregados monetarios. El frontend (y los reportes) necesitan conocer los montos totales del conjunto completo de contratos que cumple la consulta —con IVA, sin IVA, por periodo/trimestre y por año fiscal— sin tener que descargar todos los registros para sumarlos en el cliente.

## What Changes

- El endpoint `GET /api/v5/contracts` (listado) acepta un nuevo valor de `embed`, `totals`, que agrega al final de la respuesta un bloque `totals` con:
  - Total general de monto con IVA (`total_amount`).
  - Total general de monto sin IVA (`subtotal`).
  - Total por periodo/trimestre (`c_periods`) de monto con IVA.
  - Total por año fiscal (`fiscal_year`) de monto con IVA.
  - Total de resultados de la consulta (número de contratos devueltos por la consulta completa, ignorando la paginación).
- Los totales se calculan sobre el conjunto completo de resultados filtrados (filtros, `search`, joins), sin aplicar `page`/`limit`: si la consulta devuelve 500 contratos y la página tiene 50, los totales corresponden a los 500.
- La agregación se resuelve dentro del ORM actual (`DBGet`), añadiendo una acción de agregación que reutiliza el armado de joins/filtros existente; no se introducen consultas fuera del ORM.
- El documento OpenAPI publicado (`api/docs/openapi.json` y su copia en `httpdocs/api/docs/`) se actualiza para documentar el valor `totals` de `embed` y el bloque `totals` en la respuesta del GET de contratos.
- No es un cambio *breaking*: sin `?embed=totals` la respuesta es idéntica a la actual.

## Capabilities

### New Capabilities

- `contract-list-totals`: Bloque de totales monetarios y de conteo embebido en el listado del módulo de contratos, calculado sobre el conjunto completo de resultados de la consulta (ignorando paginación).

### Modified Capabilities

- `query-conventions`: Nuevo valor de embed (`totals`) aceptado por el parámetro `embed`, hasta ahora reservado a `pagination`.

## Impact

- Código: `app/api/contracts/controller.php` (declaración de agregados del módulo), `app/core/bootstrap/midelware.php` (método `get()`, bloque embed), `app/core/model/get.php` (`DBGet::Get`, nuevo modo de agregación `sum`/`totals`).
- API: `GET /api/v5/contracts?embed=totals` añade `totals` al envelope de respuesta estándar (`ApiResponse::Set('SUCCESS', ...)`).
- Base de datos: solo consultas `SUM()`/`COUNT()` sobre las tablas ya involucradas (`contratos`, joins existentes); sin cambios de esquema.
- Frontend (repo separado): podrá leer `data.totals` sin cambiar contratos existentes.
- OpenAPI: `httpdocs/api/docs/openapi.json` y `api/docs/openapi.json` (copias byte-idénticas, verificadas por `tests/OpenApiSyncTest.php`); el parámetro `embed` del GET de `/contracts` acepta y documenta `totals`.
