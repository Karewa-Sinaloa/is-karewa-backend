# Design

## Context

`GET /api/v5/contracts?embed=totals` ya emite el bloque `totals` (ver `openspec/specs/contract-list-totals/spec.md`). El middleware (`getTotals()` en `app/core/bootstrap/midelware.php`) ejecuta tantas consultas `action='sum'` como agrupaciones declare el módulo en `$get_params['totals']['groups']`, y emite cada agrupación como un arreglo plano: `totals.by_period` (agrupado por `c.period_id` + `per.name`, **sin** distinguir año fiscal) y `totals.by_fiscal_year` (agrupado por `c.fiscal_year`).

Problema: `by_period` mezcla contratos de años fiscales distintos en una sola entrada por periodo, por lo que el frontend no puede mostrar los totales de un periodo dentro de su año fiscal (punto 2 de `docs/cambios.md`).

Restricciones: usar el ORM actual (`DBGet::Get`), sin consultas fuera del ORM; los totales siguen ignorando la paginación; mantener las claves existentes del bloque (decisión (b) del usuario: no renombrar a las claves en español del ejemplo).

## Goals / Non-Goals

- **Goals**: que cada año fiscal de `by_fiscal_year` lleve sus totales por periodo anidados (`periods`), calculados sobre el conjunto completo filtrado.
- **Non-Goals**: renombrar claves del bloque `totals` (`results`, `total_amount`, `subtotal`); tocar la agregación general sin agrupar; cambiar `query-conventions`; migraciones de esquema.

## Decisions

1. **Una sola agrupación compuesta en vez de dos agrupaciones planas.**
   El módulo declara una agrupación `by_fiscal_year_period` con `group_by = ['c.fiscal_year', 'c.period_id', 'per.name']` y sums `total_amount`/`subtotal`. `DBGet` en `action='sum'` ya soporta `group_by` múltiple (concatena las expresiones al SELECT con alias sanitizado y las agrupa), por lo que **no se necesita cambiar el ORM**: una consulta `GROUP BY c.fiscal_year, c.period_id, per.name` devuelve una fila por par (año, periodo).
   *Alternativa descartada*: mantener `by_period` y filtrar en PHP por año — expondría datos agregados mal (mezcla años) o requeriría segunda pasada innecesaria.

2. **Anidamiento en el middleware, no en SQL.**
   `getTotals()` recibe las filas de la agrupación compuesta y las re-agrupa en PHP: para cada `fiscal_year` crea/actualiza la entrada de `by_fiscal_year` (suma general del año con una agregación extra `group_by = ['c.fiscal_year']`, ya existente) y añade el renglón a su arreglo `periods`. Así `by_fiscal_year` conserva exactamente sus claves actuales (`fiscal_year`, `total_amount`, `subtotal`) y solo se le agrega `periods`.
   *Alternativa*: que el módulo declare la anidación como primitiva del middleware (`'nested' => true`) — se elige solo si la composición resulta envejecida; ver Riesgos.

3. **`by_period` top-level eliminado.**
   La spec lo prohíbe explícitamente y su contenido queda cubierto por `periods`. Es **BREAKING** para lectores de `totals.by_period`; se documenta en el proposal y en el OpenAPI.

4. **Años sin periodos / periodos nulos.**
   Contratos con `period_id` NULL o `fiscal_year` NULL (posibles pese a las reglas `required`, por datos históricos): la fila agrupada llega con el grupo NULL; `getTotals()` la emite bajo `fiscal_year` null con `periods[].period_id = null`. No se descartan filas: los totales deben sumar el conjunto completo.

## Risks / Trade-offs

- [Mayor cardinalidad de filas agregadas (una por par año×periodo)] → acotado: `c_periods` tiene 4 periodos y unos pocos años fiscales; una sola consulta extra sin paginación.
- [`by_period` eliminado rompe consumidores existentes] → BREAKING marcado en el proposal; OpenAPI y tests se actualizan en el mismo cambio; el frontend (repo separado) debe migrar la lectura a `by_fiscal_year[].periods`.
- [Duplicación de la lógica de anidación si más módulos declaran `groups`] → se concentra en `getTotals()` (un solo lugar); si se anida, la anidación queda parametrizable por la declaración del módulo.
- [`per.name` en el GROUP BY] → `c_periods.name` es `NOT NULL` y con clave foránea, seguro de agrupar; se incluye para que el nombre del periodo no dependa de un `MAX()` implícito.

## Migration Plan

Sin migración de datos ni esquema. Despliegue: código + OpenAPI publicado a la vez (tests `OpenApiSyncTest` exigen copias byte-idénticas). Rollback = revertir el commit (el comportamiento sin `embed=totals` no cambia).

## Open Questions

- Si en el futuro otro módulo necesita totales anidados, ¿se generaliza la anidación como opción de declaración (`groups[].nested_by`) o se mantiene específica de contratos? No afecta a esta spec.
