# Tasks

## 1. Agrupación por tipo de procedimiento

- [x] 1.1 Declarar el grupo `by_procedure` en `$get_params['totals']['groups']` de `app/api/contracts/controller.php` (`group_by`: `c.procedure_id`, `p.name`; `fields`: `procedure_id`, `procedure_name`; `sums`: `total_amount` => `c.total_amount`) y ampliar `tests/ContractsTotalsFieldMappingTest.php` para guardarlo — verificar: `./vendor/bin/phpunit --filter ContractsTotalsFieldMappingTest` en verde y el test falle si se quita o cambia la suma a `subtotal`.
- [x] 1.2 Sembrar `c_procedures` y contratos con distintos tipos en el harness de `tests/ContractsTotalsResponseTest.php` — verificar: `./vendor/bin/phpunit --filter ContractsTotalsResponseTest` en verde con un caso nuevo que pida `GET /api/v5/contracts?embed=totals` y asserte `totals.by_procedure`: una entrada por tipo presente, con `procedure_id`, `procedure_name` y `total_amount` (con IVA) correcto.
- [x] 1.3 Cubrir en los tests de respuesta los escenarios de la spec: suma sobre el conjunto filtrado completo ignorando paginación, `by_procedure` acotado a filtros/búsqueda aplicados, tipos sin contratos ausentes, y `by_procedure: []` cuando no hay resultados — verificar: `./vendor/bin/phpunit --filter ContractsTotalsResponseTest` en verde.

## 2. Documentación OpenAPI

- [x] 2.1 Regenerar el documento OpenAPI con `php tools/generate-openapi.php` para que describa `by_procedure` dentro del bloque `totals` del listado de contratos (schema y descripción del parámetro `embed`) en `api/docs/openapi.json` y `httpdocs/api/docs/openapi.json` — verificar: `grep -c "by_procedure" api/docs/openapi.json httpdocs/api/docs/openapi.json` con coincidencias en ambas copias y `./vendor/bin/phpunit --filter OpenApiSyncTest` en verde (copias byte-idénticas).

## 3. Verificación de integración

- [x] 3.1 Correr la suite completa `./vendor/bin/phpunit` — verificar: suite en verde sin regresiones (especialmente `ContractsTotals*` y `OpenApiSyncTest`).
- [x] 3.2 Verificar el comportamiento observable con una petición real al listado (`GET /api/v5/contracts?embed=totals` en el entorno Docker, `make up`) — verificar: la respuesta JSON incluye `totals.by_procedure` con los totales con IVA por tipo y los campos existentes (`results`, `total_amount`, `subtotal`, `by_fiscal_year`) sin cambios.

## Workflow follow-up

- Marcar el item 3 del checklist en `docs/cambios.md` como transformado en especificación (hecho en la fase de propuesta; confirmar al archivar).
- Tras la implementación y revisión, archivar el cambio (`openspec archive`) y sincronizar `openspec/specs/contract-list-totals/spec.md`.
