# Design

## Context

`Recovery()` en `app/core/modules/access/local_login.php` consulta la cuenta con `DBGet` y, si no hay fila (`status_id=1` + email), responde `ApiResponse::Set(404000)` (línea 160-162); si existe, hashea un código nuevo, actualiza `recovery_code`/`recovery_date` y envía el correo. El cuerpo de respuesta distingue por tanto las cuentas registradas, a 10 peticiones/minuto (`sensitive_limit`). Además, en `Login()` la rama de usuario inexistente (línea 41 en adelante) no ejecuta `password_verify`, de modo que una petición con email inexistente responde más rápido que una con cuenta existente. Ver hallazgo F-013 y sus recomendaciones en `docs/auditoria-seguridad/auditoria-owasp-2026-10-14.md`. Ver el porqué completo en `proposal.md` — Why.

## Goals / Non-Goals

- **Goals**: idéntico cuerpo de respuesta en `Recovery` para correo desconocido/inactivo/conocido; latencia de `Recovery` acotada por un mínimo común con trabajo de verificación equivalente en la rama de cuenta inexistente; coste de verificación equivalente en `Login` cuando no existe la cuenta; ningún otro campo dependiente de la existencia.
- **Non-Goals**: cambios en el rate limiting (`api-rate-limiting`), en el contenido o la URL de los correos (cubierto por `fix-login-recovery-xss`), en la validación de payloads (`validation`), y en otras respuestas que revelan unicidad por razones de negocio (p. ej. el error de email duplicado en el alta de usuarios) — fuera del alcance de F-013.

## Decisions

1. **Respuesta de recuperación siempre simulada como éxito (mismo código, mensaje y campos), sin enviar correo cuando no hay cuenta activa.**
   - *Alternativa A*: conservar `404000` para desconocidos — es el propio hallazgo; se descarta.
   - *Alternativa B*: devolver un mismo error genérico para todos los casos — resiste la enumeración pero rompe la UX legítima ("si existe, recibirás un correo" es el contrato esperado del formulario) y el frontend ya trata el éxito como mensaje único; se descarta.
   - Elección: la única respuesta uniforme compatible con el flujo existente es el éxito simulado; el correo solo se compone y envía si hay cuenta activa.
2. **Igualación temporal de `Recovery`: mínimo de tiempo de respuesta de 500 ms aplicado a todas las ramas del endpoint + verificación simulada de coste equivalente en la rama de cuenta inexistente.**
   - *Alternativa A*: despachar el correo de forma no bloqueante para que las dos ramas tarden lo mismo — se descarta: cambiaría el contrato de `mail-delivery` («Failures are reported»: el fallo de envío deja de poder comunicarse en la respuesta) y amplía el alcance de F-013 más allá de `local_login.php:160-162`.
   - *Alternativa B*: solo `sleep` fijo sin trabajo equivalente — se descarta: el cómputo local seguiría difiriendo entre ramas.
   - *Alternativa C*: ignorar el tiempo — se descarta: el propósito de la capacidad exige igualdad también temporal.
   - Elección: el piso de 500 ms cubre el coste típico del envío en dev, y la verificación simulada iguala el cómputo local; se aplica a todas las ramas del endpoint para que el piso sea uniforme. **Asunción material**: 500 ms ≥ latencia habitual del despacho de correo en dev; si en producción el envío supera ese piso de forma sistemática, se debe subir la constante (ver riesgo).
3. **`Login`: comparación de hash falsa (*dummy hash comparison*) con algoritmo y coste idénticos a los hashes almacenados.**
   - *Alternativa A*: generar un hash falso en cada petición — añade el coste de `password_hash` (~60 ms) solo a la rama inexistente y la hace *más* lenta que la existente, creando otra distinción temporal; se descarta.
   - *Alternativa B*: responder sin verificar en la rama inexistente — es el estado actual y deja la huella; se descarta.
   - Elección: hash fijo precalculado con `PASSWORD_DEFAULT` (mismo coste que los hashes que genera la app) contra el que se ejecuta `password_verify` en la rama de usuario inexistente. **Asunción material**: los hashes de la tabla se generan con el coste por defecto; una prueba verifica que el algoritmo/coste del fijo coincide con los que emite la aplicación, y debe regenerarse si cambia `PASSWORD_DEFAULT`.
4. **Mínimo de tiempo como constante en el módulo `access`**, no como clave de configuración: evita ampliar la superficie de `app/config.yml` y de la capacidad `configuration-and-logging` para un valor único; un cambio posterior es una línea. **Asunción material**: 500 ms de latencia añadida son aceptables en un flujo interactivo poco frecuente.

## Risks / Trade-offs

- [Cuenta conocida más lenta que el piso cuando el envío de correo supera los 500 ms → la latencia seguiría distinguiendo] → mitigación: medir la latencia de envío en dev/prod y subir la constante si procede; el rate limit (10/min) acota el muestreo; el despacho no bloqueante queda documentado como alternativa si el audit lo exige después.
- [Scripts o clientes que consumían `404000` en `Recovery` dejan de detectar correos no registrados] → mitigación: marcado **BREAKING** en la propuesta; el frontend ya muestra un mensaje genérico en éxito y error; anotarlo en el changelog del despliegue.
- [El piso añade 500 ms también a errores de validación] → mitigación: se aplica uniformemente para no crear nuevas ramas de tiempo; el flujo de recuperación es interactivo y poco frecuente.
- [Constante del hash fijo diverge del coste real de los hashes almacenados] → mitigación: prueba que compara el algoritmo/coste del fijo con un hash generado por la aplicación.
- [Otras respuestas con revelación de unicidad (p. ej. alta con email duplicado)] → mitigación: declarado fuera de alcance en Non-Goals; se propone como cambio aparte si la auditoría lo reclasa.

## Migration Plan

1. Despliegue del código: sin migraciones ni claves de configuración nuevas.
2. Observación tras despliegue: registrar tiempos de `Recovery` en dev/prod para confirmar que el piso de 500 ms cubre el despacho; si no, subir la constante en un follow-up.
3. Rollback: revertir el commit (restaura `404000` y el timing actual, reintroduciendo F-013).

## Open Questions

- _(ninguno — la estrategia de igualación temporal quedó resuelta en la decisión 2 y el valor del piso en la decisión 4; cambiarlos reescribiría los escenarios de spec, por lo que no se difiere)_
