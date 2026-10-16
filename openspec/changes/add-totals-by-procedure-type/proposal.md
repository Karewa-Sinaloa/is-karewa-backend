# Proposal

## Why

El bloque `totals` del listado de contratos (`embed=totals`) hoy devuelve totales generales y agrupaciones por año fiscal/periodo, pero no desglosa el monto por tipo de procedimiento. El equipo necesita ver cuánto se contrata por licitación, adjudicación directa, etc. sobre el conjunto filtrado de la consulta, sin abrir otro endpoint ni exportar a otra herramienta.

## What Changes

- El objeto `totals` del listado de contratos incluye una nueva agrupación `by_procedure`: un arreglo con una entrada por cada tipo de procedimiento presente en los resultados filtrados, cada una con su `procedure_id`, `procedure_name` y la suma de `total_amount` (monto **con IVA**) de los contratos de ese tipo.
- Los totales por tipo de procedimiento se calculan sobre el mismo conjunto filtrado/buscado de la consulta (ignorando paginación), igual que el resto del bloque `totals`.
- El documento OpenAPI publicado documenta la nueva agrupación dentro del bloque `totals` del listado de contratos.
- Sin cambios de compatibilidad: es una adición aditiva al bloque `totals`; los campos existentes conservan nombre y forma. No es un cambio **BREAKING**.

Ejemplo de forma (decisión registrada en `design.md`): se usa un arreglo estructurado consistente con `by_fiscal_year`, no claves planas por nombre de procedimiento como sugería el ejemplo de `docs/cambios.md`, porque los tipos de procedimiento viven en la tabla `c_procedures` (datos dinámicos y editables).

```json
"totals": {
  "total_amount": 100000,
  "subtotal": 80000,
  "results": 500,
  "by_fiscal_year": [ ... ],
  "by_procedure": [
    { "procedure_id": 1, "procedure_name": "Licitación Pública", "total_amount": 60000 },
    { "procedure_id": 2, "procedure_name": "Adjudicación Directa", "total_amount": 40000 }
  ]
}
```

## Capabilities

### New Capabilities

<!-- ninguna -->

### Modified Capabilities

- `openspec/specs/contract-list-totals/spec.md`: nueva requisito "Totals grouped by procedure type" (arreglo `by_procedure` con suma de `total_amount` por tipo de procedimiento sobre el resultado filtrado, ignorando paginación), más el escenario correspondiente en la requisito de documentación OpenAPI.

## Impact

- Código: `app/api/contracts/controller.php` (bloque `'totals'` de `$get_params`, nueva agrupación `by_procedure`), y la capa del ORM que calcula los grupos de totales (`app/core/...` / `App\Model\*`) si necesita soporte nuevo (se investiga en `design.md`; el ORM actual ya soporta `groups`/`group_by`/`nest`).
- API: respuesta de `GET /api/v5/contracts?embed=totals` (aditiva).
- Documentación: `api/docs/openapi.json` y `httpdocs/api/docs/openapi.json` (copias sincronizadas; `tests/OpenApiSyncTest.php` las verifica).
- Tests: `tests/ContractsTotals*Test.php` (`ContractsTotalsQueryTest`, `ContractsTotalsResponseTest`, `ContractsTotalsFieldMappingTest`).
- Specs: `openspec/specs/contract-list-totals/spec.md` (tras sincronizar el delta).
- `docs/cambios.md`: marcar el item 3 del checklist como transformado en especificación.
