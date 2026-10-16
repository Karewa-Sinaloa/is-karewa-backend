# Proposal

## Why

El bloque `totals` del listado de contratos agrupa los totales por periodo en una lista plana (`by_period`) independiente de los años fiscales, de modo que un mismo periodo de años distintos se reporta agregado en una sola entrada y el frontend no puede mostrar —como lo requiere `docs/cambios.md` (punto 2)— los totales de cada periodo dentro del año fiscal al que pertenecen.

## What Changes

- En el bloque `totals` de `GET /api/v5/contracts?embed=totals`, cada entrada de `by_fiscal_year` incluye un sub-arreglo `periods`: una entrada por periodo presente en los contratos de ese año fiscal, con `period_id`, `period_name`, `total_amount` (con IVA) y `subtotal` (sin IVA) del periodo dentro de ese año.
- Se elimina el arreglo plano `by_period` (top-level), que ahora es redundante: su contenido queda anidado bajo cada año fiscal. **BREAKING** para cualquier consumidor que leyera `totals.by_period` (el frontend debe leer `totals.by_fiscal_year[].periods` en su lugar).
- Se conservan sin cambio las claves existentes: `totals.results`, `totals.total_amount`, `totals.subtotal` y las entradas de `by_fiscal_year` (`fiscal_year`, `total_amount`, `subtotal`); solo se añade `periods` dentro de cada año.
- La agregación por (año fiscal, periodo) se calcula sobre el conjunto completo filtrado, ignorando la paginación, igual que el resto de los totales.
- El OpenAPI publicado (`httpdocs/api/docs/openapi.json` y `api/docs/openapi.json`, copias byte-idénticas) se actualiza para reflejar la nueva forma del bloque `totals` (sin `by_period`, con `periods` anidado en `by_fiscal_year`).

## Capabilities

### New Capabilities

_(ninguna)_

### Modified Capabilities

- `contract-list-totals`: El requirement "Totals grouped by period and fiscal year" pasa de definir dos agrupaciones planas (`by_period` y `by_fiscal_year`) a definir los totales por periodo anidados dentro de cada año fiscal.

## Impact

- Código: `app/api/contracts/controller.php` (declaración de `totals.groups` en `$get_params`: la agrupación `by_period` se sustituye por una agrupación `by_fiscal_year_period` sobre `c.fiscal_year` + `c.period_id` + `per.name`), `app/core/bootstrap/midelware.php` (`getTotals()`, para anidar las filas por periodo bajo su año fiscal en vez de emitir `by_period`).
- ORM: sin cambios en `app/core/model/get.php` si la agregación existente (`action = 'sum'` con `group_by` múltiple) permite agrupar por `fiscal_year, period_id, per.name`; si no, se requerirá un ajuste menor (ver design.md).
- API: respuesta de `GET /api/v5/contracts?embed=totals` con estructura `by_fiscal_year[].periods` y sin `by_period`. **BREAKING** para lectores de `by_period`.
- Tests: `tests/ContractsTotalsFieldMappingTest.php`, `tests/ContractsTotalsQueryTest.php`, `tests/ContractsTotalsResponseTest.php` ajustados a la nueva forma.
- OpenAPI: `httpdocs/api/docs/openapi.json` y `api/docs/openapi.json` (verificadas por `tests/OpenApiSyncTest.php`).
