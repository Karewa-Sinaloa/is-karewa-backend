# Design

## Context

`ModuleHandler::Authenticate()` valida el hash declarado (`HashAuth::Validate($hash)`) y solo lo usa para eximir de JWT (`if(!$auth || ($auth && $altAuth)) return true`), de modo que con `auth=false` el hash se calcula y se ignora: `mailings index` es anónimo y `users show` declara `['hash' => true]`, un payload que el servidor nunca emite. El emisor de enlaces `loginMailer()` firma `HashAuth::Create([$code])` y lo manda como `_key` en la URL de `mailings/login-recovery`, mientras la declaración del módulo usa `['hash' => $_GET["_key"]]` — payload declarado ≠ payload firmado. La capacidad `hash-authentication` actual solo cubre la exención ("A module can opt into hash authentication"). Ver hallazgo F-009 en `docs/auditoria-seguridad/auditoria-owasp-2026-10-14.md`; motivación en `proposal.md` — Why.

## Goals / Non-Goals

- **Goals**: que un método pueda declararse hash-requerido (rechazo efectivo con auth deshabilitada), que declaración y emisión de enlaces compartan el mismo payload, y que desaparezcan las declaraciones muertas.
- **Non-Goals**: rediseñar la autenticación por sesión/JWT, cambiar la ventana de expiración o el formato del token, eximir al resto de endpoints del JWT, y proteger `users show` con hash (no hay requisito de producto que lo pida).

## Decisions

1. **Hash-requerido como estado explícito de la declaración del método, separado de `auth`**.
   - *Alternativa A*: reusar `[true, NULL, $payload]` (auth + exención por hash) — concede acceso también a cualquier portador de JWT válido, con lo que el endpoint no quedaría "solo con hash válido" y la semántica quedaría mezclada con la exención existente.
   - *Alternativa B*: marca dentro del payload (p. ej. `'required' => true`) — contamina el payload, que debe coincidir byte a byte con el firmado.
   - Elección: A es descartada por la semántica que exige la spec; B por acoplamiento. Se elige un estado explícito en la declaración (p. ej. flag junto al payload) que obligue a `HashAuth::Validate` y deniegue con el error de acceso estándar (901004) cuando falle, sin que un JWT sustituya al hash. La semántica observacional está en la spec.
2. **Payload canónico de `mailings` = `[code]` (el código de recuperación), derivado del parámetro `code`** (asunción material). El emisor ya firma `Create([$code])`, así que se cambia la **declaración** (una línea) y no el emisor.
   - *Alternativa*: cambiar el emitor a `['hash' => ...]` — obligaría a regenerar los enlaces en vuelo y a tocar el flujo de correo solo para satisfacer una declaración mal escrita.
   - El `_key` es el testigo (token), no parte del payload: sacarlo de la declaración es lo que hace que ambos lados comparen lo mismo.
3. **`users show`: eliminar `['hash' => true]` en vez de declarar un payload real**. Ningún flujo emite hash para `users show` (el enlace de registro se sirve desde `MAILINGS_URL`, no de la API) y declarar `[$id]` abriría acceso por enlace a fichas de usuario sin requisito que lo pida. Asunción registrada: el marcador no tiene consumidores (grep en el repo no encuentra usos).
4. **Denegación con el error de acceso estándar ya usado (901004)**, no con un código nuevo: consistencia con el resto de denegaciones y un solo comportamiento observable que fija la spec ("standard access-denied response").

## Risks / Trade-offs

- [Enlaces de recuperación en vuelo al desplegar] → el payload de emisión no cambia, solo el de declaración: los enlaces vigentes (ventana de 1 h) siguen validando; test de round-trip con la URL exacta que construye `loginMailer`.
- [La descarga interna `file_get_contents(API_URL . 'mailings/login-recovery?...')` pierda el acceso] → la URL ya incluye `_key`; la tarea de integración verifica que el correo de recuperación se sigue generando.
- [Otras declaraciones de hash en el repo no inventariadas] → grep/inventario en las tareas; solo `mailings` y `users` declaran hash hoy.
- [Cambiar `mailings` a hash-requerido rompe pruebas manuales/herramientas que llamen el endpoint sin enlace] → es justamente el efecto **BREAKING** declarado; el health de plantillas debe usar enlaces emitidos.

## Migration Plan

Despliegue único (código + declaraciones), sin migración de datos. Los enlaces vigentes siguen válidos. Rollback: revertir el commit; no deja estado persistido.

## Open Questions

- _(ninguno — el mecanismo, el payload canónico y la eliminación del marcador se decidieron arriba; si se decide proteger `users show` con hash, abrir un cambio nuevo porque añadiría un requisito distinto)_
