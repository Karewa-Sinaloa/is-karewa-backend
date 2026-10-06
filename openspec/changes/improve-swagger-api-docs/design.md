# Design

## Context

El contrato se genera con `tools/generate-openapi.php`, que parsea `$moduleFields`/`$get_params` de los controllers, pero:

- La seguridad se hardcodea: solo `store`/`update`/`destroy` reciben `security` (con `[true]` como placeholder), ignorando el `$accepted_methods` real de cada `index.php` de módulo.
- `$apiRoutes` es una lista fija de rutas que ya no coincide con los módulos (`/hcaptcha` ausente; `attachments`/`frontend-logs` reciben `GET` colección aunque solo aceptan `store`).
- Las respuestas de error se escriben inline por operación desde una lista `$selected` fija de códigos; `902003` y `429000` no están en ella, y no hay `components/responses`.
- Los `200` de éxito solo tienen `description`, sin schema; no hay `operationId`.
- El middleware ya no devuelve `404` en listados vacíos (devuelve `200 []`), pero la spec aún documenta `404` en 19 operaciones de colección.
- No existe ninguna test que compare el JSON publicado con lo que produce el generator ni con los módulos.

Ver `proposal.md` para la motivación y `specs/swagger-scalar-api-docs/spec.md` para el contrato exigido.

## Goals / Non-Goals

**Goals:**

- Que el JSON publicado sea una proyección fiel de los módulos: campos, operaciones aceptadas y declaraciones de autenticación.
- Reducir el tamaño y la duplicación del documento vía `components/responses` + `$ref`.
- Hacer el desfase entre módulos y JSON detectable automáticamente por el suite de tests.
- Mantener OpenAPI `3.0.3` y las rutas de publicación actuales (`api/docs/` y `httpdocs/api/docs/`).

**Non-Goals:**

- Cambiar comportamiento de runtime de la API (envelope, códigos, rutas, autenticación).
- Cambiar la UI Scalar ni su hosting (incluido el futuro GitHub Pages).
- Documentar roles requeridos por operación con extensión `x-` (posible futura mejora).
- Versionado semántico del contrato ni publicación desde CI.

## Decisions

**1. Derivar seguridad del `$accepted_methods` de cada `index.php`, no de una lista fija.**
El generator ya lee los controllers; extenderlo para leer también el bloque `$accepted_methods` del `index.php` del módulo (mismo parser por bloques que usa para `$moduleFields`) da la fuente de verdad única. Alternativa descartada: un mapa manual en el generator — se desfase de nuevo, que es exactamente el problema actual.

**2. Reconciliar `$apiRoutes` con los módulos reales en lugar de parsear `routes.php`.**
Se mantiene la tabla explícita (mantiene el control sobre paths y summaries y evita parsear la definición de rutas), pero se corrige: quitar `GET` colección donde el módulo no acepta `index`, añadir `hcaptcha` (`POST`), y validar en test que cada entrada coincide con el `$accepted_methods`/métodos del módulo. Alternativa descartada: inferir todo de los módulos — `access` y `docs` tienen rutas especiales (`/access/login`, `/docs`) que no siguen la convención `/{module}` y seguirían necesitando tabla.

**3. Respuestas de error compartidas en `components/responses`.**
Cada código del catálogo genera una entrada única (`Error404000`, `Error429000`, …) y las operaciones referencian con `$ref`. Alternativa descartada: mantener inline (actual, ~778 KB con ejemplos repetidos) y `schemas` por módulo completo (mucho más trabajo, aporta menos claridad que los ejemplos ya existentes).

**4. Selección de códigos por operación a partir del catálogo + `$selected`, ampliada.**
`$selected` se amplía con `902003` y `429000` (y se mantiene el filtro por `kind`). El catálogo `api_codes.yml` sigue siendo la fuente de verdad de mensaje/HTTP/code.

**5. Semántica de listados: `200 []` documentado, `404` solo en ITEM.**
Refleja el cambio ya implementado en `midelware.php::get()`. El generator deja de añadir `404000` a operaciones de colección `GET` y lo conserva en ITEM.

**6. `operationId` determinista.**
`{method}_{path}_{module}` normalizado (p. ej. `get_users_list`, `post_hcaptcha_store`), calculado solo a partir de module/path/method, de modo que regenerar sin cambios no lo altera.

**7. Test de sincronización en dos niveles.**
(a) *Contraproyección*: regenerar a un stream/tmp y comparar con el JSON publicado — detecta "cambió un controller y no se regeneró". (b) *Alineación con módulos*: aserciones puntuales (config expone `value`, sin `GET /attachments`, `POST /hcaptcha` presente, security coincide con `$accepted_methods` de una muestra de módulos) — detecta errores del generator mismo. Se sigue la convención de `openspec/specs/testing` (PHPUnit en `tests/`, bootstrap explícito).

## Risks / Trade-offs

- [Parser de `index.php` frágil ante formato libre] → se limita a extraer el bloque `$accepted_methods` con el mismo enfoque de llaves balanceadas ya usado para `$moduleFields`, y el test de alineación falla si el bloque no se pudo leer.
- [La test de regeneración es sensible a orden de claves/JSON] → se compara el JSON decodificado (estructura), no los bytes.
- [`$ref` a `components/responses` cambia el aspecto del documento para consumidores que lo parsean a mano] → es una adición compatible con OpenAPI 3.0.3; los ejemplos siguen accesibles vía la referencia.
- [Cambiar `security` en operaciones existentes puede confundir a integradores] → es una corrección hacia la verdad (hoy faltan `401` en listados autenticados y sobra en `POST /users`); se nota en el proposal como BREAKING solo a nivel documental.
- [Dos copias del JSON (`api/docs` y `httpdocs/api/docs`)] → el generator sigue escribiendo ambas; la test verifica que sean idénticas para evitar deriva.

## Migration Plan

1. Regenerar el JSON con el generator corregido y revisar el diff (informativo, no funcional).
2. Commit del generator + JSON regenerado + tests en un solo paso (el JSON versionado debe cambiar en el mismo commit que el generator, para que la test de sincronización nunca falle en `main`).
3. Rollback: revert del commit; el JSON publicado vuelve al anterior sin efectos en runtime.

## Open Questions

- Ninguno; la publicación del contrato desde CI/GitHub Pages queda fuera por diseño (Non-Goal).
