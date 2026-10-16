# Tasks

## 1. Soporte de agregados en el ORM

- [x] 1.1 Agregar a `app/core/model/get.php` (`DBGet::Get()`) una acción de agregación (ej. `sum`) que, reutilizando `get_joins()`/`get_filters()`, devuelva `ROUND(SUM(campo),2)` por cada campo declarado más un conteo de registros, sin aplicar `LIMIT` y sin salirse del ORM. Verificar con un test PHPUnit de `DBGet` que una agregación sobre datos de prueba devuelve la suma y el conteo correctos (test nuevo en `tests/`, p. ej. `ContractsTotalsQueryTest.php`).
- [x] 1.2 Extender la acción de agregación para aceptar agrupamientos fijos (`GROUP BY` sobre columnas del módulo, p. ej. `period_id`, `fiscal_year`) devolviendo una fila por grupo con su suma y la columna de agrupación. Verificar con el mismo test PHPUnit que el `GROUP BY` devuelve un renglón por grupo con la suma correcta.

## 2. Declaración de totales en el módulo de contratos

- [x] 2.1 Declarar en `app/api/contracts/controller.php` la configuración de totales del módulo: campos monetarios (`total_amount` con IVA, `subtotal` sin IVA) y agrupaciones (período `period_id` con nombre desde `c_periods`, año fiscal `fiscal_year`). Verificar con un test PHPUnit de mapeo de campos del módulo (p. ej. `ContractsTotalsFieldMappingTest.php`) que la declaración lista los campos y agrupaciones esperados.
- [x] 2.2 Confirmar que el resto de módulos, al no declarar totales, no se ven afectados. Verificar con `./vendor/bin/phpunit` (suite completa) que no hay regresiones.

## 3. Bloque embebido `totals` en el middleware

- [x] 3.1 En `app/core/bootstrap/midelware.php::get()`, reconocer `embed=totals`: si el módulo declara agregados, ejecutar la agregación con los mismos `$get_params['filters']`, `joins` y búsqueda del listado (sin paginar) y añadir `$data['totals']` (totales generales con IVA y sin IVA, totales por período, totales por año fiscal y conteo de resultados). Verificar con un test PHPUnit (p. ej. `ContractsTotalsResponseTest.php`) que `embed=totals` agrega el bloque con valores calculados sobre el conjunto completo filtrado y no sobre la página.
- [x] 3.2 Ajustar el camino de "sin resultados" de `get()` para que, cuando se pidieron totales, la respuesta incluya `data: []` y `totals` con importes en cero y conteo 0. Verificar con el test del punto 3.1 usando filtros que no matcheen ningún contrato.
- [x] 3.3 Verificar que sin `embed=totals` la respuesta del listado de contratos es idéntica a la actual (sin bloque `totals`) con un test PHPUnit de regresión.
- [x] 3.4 Verificar el filtrado: con `?embed=totals` y filtros/búsqueda aplicados, los totales y el conteo cubren solo los contratos que cumplen la consulta (test PHPUnit con filtro `eq` y con `search`).

## 4. Documentación OpenAPI y verificación final

- [x] 4.1 Documentar `embed=totals` en el parámetro `embed` del GET de `/contracts` en `httpdocs/api/docs/openapi.json`: añadir `totals` al `enum`, explicar en la descripción que agrega el bloque de totales calculado sobre el conjunto completo filtrado (ignorando paginación). Verificar con `./vendor/bin/phpunit tests/OpenApiSyncTest.php` que el documento sigue siendo válido y sincronizado.
- [x] 4.2 Documentar el bloque `totals` en la respuesta del GET de `/contracts` (ejemplo y/o esquema del `ResponseEnvelope`): suma con IVA, suma sin IVA, totales por período, totales por año fiscal y conteo de resultados. Verificar con `./vendor/bin/phpunit tests/OpenApiSyncTest.php`.
- [x] 4.3 Sincronizar la copia publicada `api/docs/openapi.json` con `httpdocs/api/docs/openapi.json` (byte-idénticas). Verificar con `./vendor/bin/phpunit tests/OpenApiSyncTest.php` (compara ambas copias).
- [x] 4.4 Ejecutar la suite completa con `./vendor/bin/phpunit` y confirmar que pasa sin regresiones.

## Workflow follow-up

- (Opcional) Ejecutar `openspec validate --strict` y, tras la implementación y aprobación, archivar el cambio con `openspec archive add-contract-list-totals` para fusionar los deltas en `openspec/specs/`.
- Marcar el elemento del checklist en `docs/cambios.md` como implementado una vez aplicado el cambio.
