# Tasks

## 1. Agregación por (año fiscal, periodo) en el ORM/módulo

- [x] 1.1 Sustituir la agrupación `by_period` de `$get_params['totals']['groups']` en `app/api/contracts/controller.php` por una agrupación `by_fiscal_year_period` con `group_by = ['c.fiscal_year', 'c.period_id', 'per.name']`, `fields` (`fiscal_year`, `period_id`, `period_name`) y sums `total_amount`/`subtotal`; mantener `by_fiscal_year` como está. Verificar: `php -l app/api/contracts/controller.php` sin errores y que `tests/ContractsTotalsFieldMappingTest.php` (tras ajuste en 3.1) pase.
- [x] 1.2 Confirmar que `DBGet` (`action='sum'`) soporta `group_by` de tres expresiones sin cambios; si el alias sanitizado de `per.name` colisionara, ajustar `alias_key()` de `app/core/model/get.php` mínimamente. Verificar: `./vendor/bin/phpunit --filter ContractsTotalsQueryTest` en verde.

## 2. Anidamiento en el middleware

- [x] 2.1 Modificar `getTotals()` en `app/core/bootstrap/midelware.php` para consumir la agrupación compuesta: construir `by_fiscal_year` con las claves actuales (`fiscal_year`, `total_amount`, `subtotal`) más un arreglo `periods` (entradas con `period_id`, `period_name`, `total_amount`, `subtotal` sumados solo de ese año), y dejar de emitir `by_period`. Verificar: `./vendor/bin/phpunit --filter ContractsTotalsResponseTest` en verde tras los ajustes de 3.2.
- [x] 2.2 Manejar grupos con `fiscal_year` o `period_id` NULL (datos históricos): emitirlos con valor `null` sin descartarlos de las sumas. Verificar: caso de prueba nuevo en `ContractsTotalsResponseTest` que inserta un contrato sin periodo y comprueba que su monto aparece en `by_fiscal_year` y no se pierde del total general.

## 3. Tests

- [x] 3.1 Ajustar `tests/ContractsTotalsFieldMappingTest.php`: la declaración `groups` espera `by_fiscal_year` y `by_fiscal_year_period` (sin `by_period`) con el `group_by` de tres columnas. Verificar: `./vendor/bin/phpunit --filter ContractsTotalsFieldMappingTest`.
- [x] 3.2 Ajustar y ampliar `tests/ContractsTotalsResponseTest.php`: cada `by_fiscal_year[]` tiene `periods`; el mismo `period_name` en dos años fiscales reporta montos independientes; no existe `by_period` top-level; `results`, `total_amount`, `subtotal` siguen intactos. Verificar: `./vendor/bin/phpunit --filter ContractsTotalsResponseTest`.
- [x] 3.3 Ejecutar la suite completa: `./vendor/bin/phpunit` en verde.

## 4. OpenAPI

- [x] 4.1 Actualizar `httpdocs/api/docs/openapi.json` y `api/docs/openapi.json`: el esquema/ejemplo del bloque `totals` del GET de `/contracts` refleja `by_fiscal_year[].periods` y ya no documenta `by_period`; la descripción del parámetro `embed` menciona los totales anidados por año fiscal. Verificar: `./vendor/bin/phpunit --filter OpenApiSyncTest` (copias byte-idénticas) y `./vendor/bin/phpunit` completo.

## Workflow follow-up

- Al finalizar: `/openspec-apply-change` para implementar; después `/openspec-verify-change` y `/openspec-archive-change` para sincronizar la delta de `contract-list-totals` a la spec principal.
