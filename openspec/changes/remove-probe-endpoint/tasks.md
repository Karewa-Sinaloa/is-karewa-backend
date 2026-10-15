# Tasks

## 1. Eliminación del endpoint de diagnóstico

- [ ] 1.1 Borrar `httpdocs/probe4.php` y verificar que la raíz publicada ya no lo contiene — `test ! -f httpdocs/probe4.php` (y el test nuevo `tests/WebRootHygieneTest.php` lo afirma) — `./vendor/bin/phpunit --filter WebRootHygieneTest`
- [ ] 1.2 Verificar con el stack local levantado (`make up`) que la ruta responde 404 y no devuelve IPs ni cabeceras de proxy — `curl -s -o /dev/null -w '%{http_code}\n' http://localhost/probe4.php` → `404`

## 2. Guardia contra la reintroducción

- [ ] 2.1 Añadir `tests/WebRootHygieneTest.php` con lista blanca de los `.php` publicables (`api.php`, `index.php`) y verificación de que no hay scripts de diagnóstico en `httpdocs/` — `./vendor/bin/phpunit --filter WebRootHygieneTest`
- [ ] 2.2 Extender ese test con la detección de cadenas de diagnóstico (`REMOTE_ADDR`, `HTTP_CF_CONNECTING_IP`, `cf_connecting_ip`) en cualquier script servible desde la raíz, y verificar que falla ante un fixture temporal que las contenga — `./vendor/bin/phpunit --filter WebRootHygieneTest`
- [ ] 2.3 Verificar que la guardia corre dentro de la suite habitual (aparece en la salida de `./vendor/bin/phpunit`) para que `make test` la ejecute sin pasos adicionales

## 3. Documentación del healthcheck

- [ ] 3.1 Documentar en `SECURITY.md` que `/healthz` (y los endpoints autenticados) es la única vía de diagnóstico y que la raíz web no puede contener sondas, y verificar con `grep -n "healthz" SECURITY.md` que la sección quedó escrita

## 4. Verificación de integración

- [ ] 4.1 Ejecutar la suite completa `./vendor/bin/phpunit` y verificar que pasa sin regresiones
- [ ] 4.2 Verificar con `openspec validate remove-probe-endpoint` que el cambio es válido
