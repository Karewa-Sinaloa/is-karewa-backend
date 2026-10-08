# Tasks

## 1. Reglas y esquema de solicitud

- [x] 1.1 Parsear el bloque `$rules` de cada módulo en `tools/generate-openapi.php` y verificar que `php tools/generate-openapi.php` produce un JSON válido con las reglas de `users`, `contracts` y `materias`.
- [x] 1.2 Generar los cuerpos de `POST`/`PUT` solo con campos enviables (`saved` y no `readonly`) y verificar que `id`, `created_at`, `*_backup` y los campos de join `*_name` ya no aparecen en `POST /contracts`.
- [x] 1.3 Declarar `required` en `POST` a partir de las reglas con `required` y verificar que `POST /users` declara `email`, `first_name` y `last_name`, y que `PUT /users/{id}` no declara `required`.
- [x] 1.4 Documentar tipo, formato y restricciones de cada propiedad desde las reglas y verificar `maxLength`/`minLength`/`minimum`, `format: email` y `format: date` en los campos correspondientes.
- [x] 1.5 Emitir `properties` como objeto JSON cuando el módulo no tiene campos y verificar `"properties": {}` en `POST /attachments`.

## 2. Parámetros de consulta

- [x] 2.1 Enumerar en `fields`, `sort` y `groupby` los campos que acepta cada módulo y verificar la lista en `GET /roles` y `GET /contracts`.
- [x] 2.2 Restringir `embed` a `pagination` y derivar los ejemplos de `sort`/`groupby` de campos reales del módulo y verificar que no quedan ejemplos con campos inexistentes.
- [x] 2.3 Describir cada filtro con su tipo, sus reglas y si es de solo lectura, y verificar que la descripción de `name` en `GET /roles` ya no dice `Required.`.
- [x] 2.4 Quitar el espacio del ejemplo de `search` (`texto de prueba` → `texto`) y usar un solo campo en los ejemplos de `sort`/`fields` cuando el módulo tiene uno, verificando que el ejemplo de `GET /mailings` ya no es `+id,-id` ni `id,id`.

## 3. Muestras de código legibles

- [x] 3.1 Adjuntar `x-codeSamples` (cURL + axios) a las 89 operaciones desde el bucle final de `tools/generate-openapi.php`, con `build_code_samples()`, `build_curl_source()`, `build_axios_source()` y `js_literal()`.
- [x] 3.2 Poner `hiddenClients: true` en `httpdocs/api/docs/index.html` para ocultar los clientes que Scalar genera con los ejemplos percent-encodificados.
- [x] 3.3 Verificar con CDP sobre la página real que el selector de lenguaje ofrece `cURL` y `axios`, que la muestra de `GET /roles` se lee `sort=+id,-name&fields=id,name&name=lk:texto`, y que la página no contiene `%2C`, `%3A`, `%2B` ni `%20` visibles.

## 4. Publicación y verificación

- [x] 4.1 Regenerar `api/docs/openapi.json` y `httpdocs/api/docs/openapi.json` y verificar que ambos archivos son idénticos y que las 89 operaciones tienen las dos muestras.
- [x] 4.2 Ejecutar `./vendor/bin/phpunit` y verificar las mismas métricas que la línea base (159 tests, 5169 assertions).
- [x] 4.3 Comprobar que `https://kapi.chavodigital.com/api/docs/` sirve el `index.html` con `hiddenClients: true` y el documento con las muestras nuevas.
