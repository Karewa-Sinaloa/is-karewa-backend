# Design

## Context

Hoy hay tres reglas distintas para "poner" una contraseña y una cuarta para verificarla: `store` fuerza `required|min:8` (`app/core/modules/users/controller.php:100`), `update` usa la regla de clase `password => min:6` (`:62`, por lo que **permite debilitar** la contraseña), `Reset` exige `required|min:8` (`app/core/modules/access/local_login.php:239`) y `Login` valida con `required|min:6` (`:41`). `FieldsValidator` soporta `min` como longitud (`app/core/validation/fields.php:77`). Ver hallazgo F-018 en `docs/auditoria-seguridad/auditoria-owasp-2026-10-14.md`; motivación en `proposal.md` — Why.

## Goals / Non-Goals

- **Goals**: una sola fuente de política aplicada en `store`, `update` y `Reset`; impedir debilitar contraseñas; mantener el acceso de credenciales existentes intacto.
- **Non-Goals**: rotación forzada de contraseñas existentes, cambiar el algoritmo de hash (`password_hash`/bcrypt; Argon2id queda para otro cambio), MFA, y evaluar contraseñas contra listas de filtraciones externas (Have I Been Pwned u on-line).

## Decisions

1. **Longitud mínima: 10 caracteres** (dentro del rango 10-12 que sugiere la auditoría).
   - *Alternativa A*: 8 — por debajo de la recomendación de la auditoría y apenas NIST SP 800-63B; no corrige el hallazgo.
   - *Alternativa B*: 12 — más resistencia offline, pero más fricción y abandono en un panel de monitorización; el margen que gana 2 caracteres queda cubierto por el bloqueo de comunes del punto 3.
   - Elección: 10 es el valor menos restrictivo que satisface la auditoría y maximiza la ganancia de seguridad por carácter añadido; se aplica solo al **fijar** contraseña, así que no bloquea a nadie existente.
2. **Sin reglas de complejidad** (mayúsculas/dígitos/símbolos obligatorios).
   - *Alternativa*: exigir complejidad — la propia auditoría prefiere longitud + bloqueo de comunes; NIST desaconseja las reglas de composición porque empujan a sustituciones predecibles (`Password1!`) que el diccionario cubre igualmente.
   - Elección: no incluirlas; la spec solo fija longitud y lista de comunes, de modo que añadirlas después sería un cambio de requisito consciente.
3. **Bloqueo de contraseñas comunes incluido, con lista curada empaquetada con la aplicación** (p. ej. recurso en `resources/`/`app/core/config/`).
   - *Alternativa*: posponerlo — descartada: la auditoría lo prefiere precisamente a la complejidad y, con solo longitud, claves de 10+ muy probadas (`1234567890`, teclados) quedarían aceptadas.
   - Elección: lista estática curada (cientos de entradas, no miles), comparación insensible a mayúsculas tras normalizar; sin servicios externos. Asunción material registrada aquí.
4. **Fuente única de la política**: una constante/helper compartido del que beben los tres flujos (`store`, la regla que aplica `update` cuando hay contraseña, y `Reset`). La deriva actual entre `min:6` y `min:8` es exactamente el bug; duplicar la cadena `min:10` en tres sitios lo reproduciría.
   - *Alternativa*: dejar las tres reglas sueltas y añadir un test que las compare — se descarta como control primario (el test vigila, no define); se mantiene además un test que fija las tres.
5. **`Login` (`local_login.php:41`) no sube el mínimo**: verificar una credencial existente no es fijar una política; subirlo allí bloquearía el acceso de cuentas con contraseñas de 6-9 caracteres (regresión de disponibilidad). Queda registrado como asunción material y cubierto por su propio requisito y test.
6. **`update` sigue sin requerir contraseña**: solo se valida si el payload la lleva (no obligar a cambiarla en cada edición).

## Risks / Trade-offs

- [Cuentas existentes con contraseñas cortas siguen siendo más débiles] → mitigación: la política se aplica en el próximo alta/actualización/reset; una rotación forzada queda fuera de scope (Non-Goal) y puede abrirse como cambio aparte.
- [La lista de comunes envejece o se queda corta] → mitigación: el requisito es de comportamiento (la lista vive en un recurso versionado), se puede ampliar sin tocar código; documentar el criterio de curación junto al recurso.
- [Tests existentes que asumen `min:8` en `store` o `min:6` en `update`] → la suite completa del grupo de integración los detectará; actualizar las expectativas en los propios tests de cada flujo.
- [Falsa sensación de seguridad por política sin fuerza bruta en línea] → el rate limiting del login ya cubierto por `api-rate-limiting` sigue siendo el control correspondiente; fuera de este cambio.

## Migration Plan

Sin migración de datos: los hashes almacenados no cambian y la política solo evalúa contraseñas nuevas. Despliegue en un paso; rollback = revertir el commit (no deja estado).

## Open Questions

- _(ninguno — longitud (10), ausencia de complejidad, inclusión del bloqueo de comunes y el alcance sobre `Login` se decidieron arriba; si negocio pide 12 o complejidad, actualizar la spec y este diseño antes de implementar)_
