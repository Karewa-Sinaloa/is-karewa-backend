# Tasks

## 1. Security derivada de los módulos

- [x] 1.1 Extraer el bloque `$accepted_methods` de cada `index.php` de módulo en `tools/generate-openapi.php` con parser de llaves balanceadas y verificar con un script puntual que devuelve las entradas esperadas para `users`, `roles`, `config` y `contracts`
- [x] 1.2 Emitir `security: [{bearerAuth: []}]` solo en operaciones cuyo método declara autenticación requerida, y regenerar el JSON para verificar que `GET /users` y `GET /roles` lo llevan mientras que `POST /users` y `GET /config` no
- [x] 1.3 Añadir test PHPUnit que contrasta el `security` documentado de una muestra de operaciones contra el `$accepted_methods` real de sus módulos y verificar `./vendor/bin/phpunit tests/OpenApiSyncTest.php`

## 2. Inventario de operaciones

- [x] 2.1 Corregir `$apiRoutes`: quitar `GET` colección de `attachments` y `frontend-logs`, mantener `PUT/DELETE` donde el módulo sí los implementa, y verificar que el JSON regenerado ya no incluye `GET /attachments` ni `GET /frontend-logs`
- [x] 2.2 Añadir la ruta `hcaptcha` (`POST`, autenticado, rol 1) con su requestBody vacío y respuesta con `hcaptcha_bypass`, y verificar que `POST /hcaptcha` aparece documentado con su ejemplo
- [x] 2.3 Añadir test que recorre `$apiRoutes` y falla si una operación documentada no existe en el `$accepted_methods`/clase del módulo o si un método aceptado falta en la spec, y verificar con `./vendor/bin/phpunit tests/OpenApiSyncTest.php`

## 3. Catálogo de errores y semántica de listados

- [x] 3.1 Ampliar `$selected` con `902003` y `429000` en `add_error_responses()` y verificar que los nuevos códigos aparecen en las respuestas de operaciones CRUD y en todas las rutas con rate limit
- [x] 3.2 Dejar de inyectar `404000` en operaciones de colección `GET` conservándolo en ITEM, y verificar que los listados documentan `200` con `data: []` y ningún `404`
- [x] 3.3 Añadir test que valida que todo código documentado existe en `api_codes.yml` con su `http_code` coincide y que ningún listado de colección documenta `404`, y verificar con `./vendor/bin/phpunit tests/OpenApiSyncTest.php`

## 4. Calidad del contrato

- [x] 4.1 Generar `components/responses` con una entrada por código documentado y reemplazar las respuestas inline por `$ref`, y verificar que el JSON resultante es estructuralmente válido (`python3 -m json.tool`) y reduce su tamaño
- [x] 4.2 Añadir `operationId` determinista (`{method}_{path}` normalizado) a todas las operaciones y verificar que dos regeneraciones consecutivas producen el mismo set de identificadores
- [x] 4.3 Declarar schema de envelope (`message`, `code`, `http_code`, `data`, `meta`) en cada respuesta de éxito (200/201/202) y verificar que ninguna respuesta de éxito queda sin `content.application/json.schema`
- [x] 4.4 Añadir test que verifica unicidad de `operationId`, presencia de schema en respuestas de éxito y que ambos `openapi.json` publicados son idénticos, y verificar con `./vendor/bin/phpunit tests/OpenApiSyncTest.php`

## 5. Verificación de sincronización y regeneración

- [x] 5.1 Añadir test de contraproyección: regenerar la spec a un archivo temporal con el generator y comparar estructura con `api/docs/openapi.json`, y verificar que falla si se edita un `$moduleFields` sin regenerar
- [x] 5.2 Ejecutar `php tools/generate-openapi.php`, validar la coincidencia de ambas copias publicadas y revisar el diff del JSON en busca de regresiones inesperadas
- [x] 5.3 Ejecutar la suite completa `./vendor/bin/phpunit` y verificar que todo pasa sin regresiones
