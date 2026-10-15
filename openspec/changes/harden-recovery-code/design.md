# Design

## Context

`Recovery()` genera el código con `$rec_code = mt_rand(100000, 999999)` y lo guarda hasheado con `password_hash` (app/core/modules/access/local_login.php:170-171). `Reset()` verifica con `password_verify` y comprueba la TTL (`recovery_date` + `REC_CODE_TIME` = 3600 s, línea 266-273), pero no cuenta intentos fallidos ni distingue intentos repetidos: dentro de la ventana solo el rate limit global del endpoint (10/min, `sensitive_limit`) acota la fuerza bruta (~600 intentos/ventana). Ver hallazgo F-006 en `docs/auditoria-seguridad/auditoria-owasp-2026-10-14.md`. Ver motivación en `proposal.md` — Why.

El contrato de columnas existente está especificado en `openspec/specs/access-recovery-fields/spec.md` (Reset escribe solo `password`, `recovery_date`, `recovery_code`) y no se modifica con este cambio.

## Goals / Non-Goals

- **Goals**: PRNG criptográfico en la generación; tope duro de 5 intentos fallidos por código; invalidación tras uso exitoso; mantener TTL de 3600 s y el formato de 6 dígitos.
- **Non-Goals**: cambiar el formato del código a enlace/token largo (decisión 1), cambiar la plantilla de correo o la URL de `mailings/login-recovery` (cubierto por `fix-login-recovery-xss` y F-015), reducir la TTL, y la igualación de tiempos de login (cubierta por `prevent-account-enumeration`).

## Decisions

1. **Código numérico de 6 dígitos generado con `random_int` (CSPRNG) + tope de 5 intentos, en lugar de token de ≥128 bits.**
   - *Alternativa A (la que prefiere la auditoría)*: token de ≥128 bits en el enlace de un solo uso. Más entropía bruta, pero es **BREAKING** en varios contratos a la vez: la validación de `Reset` exige `numeric|min_value:100000|max_value:999999`, el formulario del frontend (repo separado) espera 6 dígitos, y la URL de `mailings/login-recovery` cambia de forma —justo el fichero que también toca el cambio `fix-login-recovery-xss`, con riesgo de solape.
   - *Alternativa B*: longitud intermedia (8 dígitos) — mismo coste de ruptura que A con menos beneficio.
   - Elección: con el tope duro de 5 intentos por código, la probabilidad de éxito de fuerza bruta en una ventana es ≤ 5/10⁶ = 5×10⁻⁶ (y el rate limit global la reduce aún más); el tope, no la entropía, es el control fuerte. **Asunción material**: el requisito de "suficiente entropía" se satisface relativa al tope de intentos; si en el futuro se elimina el tope, el formato debe migrarse a token largo (cambio aparte, **BREAKING**).
2. **Contador de intentos en una tabla propia (`recovery_attempts`, `user_id` único) en lugar de una columna en `users`.**
   - *Alternativa A*: columna `recovery_attempts` en `users` — requeriría modificar `access-recovery-fields` (su escenario fija que Reset escribe solo `password`, `recovery_date`, `recovery_code`) y migración sobre la tabla de usuarios; se descarta para no tocar esa capacidad.
   - *Alternativa B*: contador en memoria/`apcu` — el estado por petición de PHP-FPM no es duradero entre peticiones; se descarta.
   - Elección: tabla pequeña siguiendo el patrón de `user_sessions`/`token_blacklist` (`resources/migrations/`), escrita solo por el flujo `access`.
3. **Invalidar limpiando `recovery_code`/`recovery_date` al superar el tope** (mismo camino que el uso exitoso), de modo que los intentos posteriores fallan con el error de código inválido existente (901005) sin añadir códigos de respuesta nuevos ni revelar que se alcanzó el límite.
4. **El contador se reinicia al emitir un código nuevo y al usarlo con éxito** (borrado de la fila), para que cada código tenga su propia ventana de 5 intentos; la TTL y el rate limit global se mantienen como capas adicionales.

## Risks / Trade-offs

- [Usuario legítimo que falla 5 veces seguidas se queda sin código] → mitigación: puede solicitar un código nuevo (resetea el contador); se documenta en el escenario de spec; el flujo de recuperación ya implica esperar correo.
- [Migración nueva (`recovery_attempts`) puede no estar aplicada en un entorno] → mitigación: patrón de migración SQL existente en `resources/migrations/`; verificar en el task de integración que la suite y el entorno dev la crean.
- [El tope por código no evita distribuir intentos entre muchos códigos (solicitar recovery repetidamente)] → mitigación: cada solicitud invalida el código anterior y la ventana de TTL es de 1 h; el rate limit global (10/min) acota la emisión; se registra como límite aceptado.
- [Elección de formato numérico frente a la recomendación del enlace largo de la auditoría] → mitigación: decisión 1 deja documentada la ruta de migración a token si retira el tope de intentos; el requisito de spec solo exige fuente criptográfica + límite, no el formato.

## Migration Plan

1. Aplicar la migración `resources/migrations/recovery_attempts.sql` en dev y producción antes (o junto) al despliegue del código.
2. Despliegue del código: sin cambios en el contrato visible salvo el nuevo tope de 5 intentos (códigos ya emitidos siguen siendo válidos hasta su TTL).
3. Rollback: revertir el código; la tabla nueva queda huérfana e inocua (puede eliminarse con su migración inversa).

## Open Questions

- _(ninguno — la elección de formato numérico vs. token largo se resolvió en la decisión 1; cambiarla reescribiría los requisitos de spec y las tareas, por lo que no se difiere)_
