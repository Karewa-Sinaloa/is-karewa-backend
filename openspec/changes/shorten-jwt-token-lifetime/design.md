# Design

## Context

El login llama siempre `SessionSet::Login($login_data, true)` (app/core/modules/access/local_login.php:91), y `jwtToken::encode()` fija `exp = time() + 31536000` cuando `keep_session` es true (app/core/auth/jwt_token.php:40-42), de modo que todo token dura 1 año e ignora `SESSION_TIME` (86400 s, app/config.yml:16). La validación por petición (`SessionSet::Validate`, invocada desde `ModuleHandler::Authenticate()` en app/core/auth/module.access.controller.php:78) comprueba firma, expiración, sesión activa y lista negra, pero define `USER_ROLE` desde el claim del token sin consultar la BD (app/core/auth/session.set.php, `applySession()`). Ver hallazgo F-004 en `docs/auditoria-seguridad/auditoria-owasp-2026-10-14.md`. Ver motivación en `proposal.md` — Why.

## Goals / Non-Goals

- **Goals**: que la vida del access token por defecto sea `SESSION_TIME`; que la vida extendida solo ocurra por opt-in explícito del usuario; revalidar `role_id`/`status_id` en cada petición autenticada y revocar la sesión si difieren del token.
- **Non-Goals**: introducir refresh tokens (la auditoría lo sugiere, pero fuera del alcance de este cambio), alterar el mecanismo de sesión única/lista negra existente, cambiar la autenticación por hash alternativa, cambiar la política de contraseñas o el flujo de recuperación.

## Decisions

1. **Opt-in de sesión prolongada como campo opcional del payload de login** (`keep_session`, booleano, por defecto false).
   - *Alternativa A*: cabecera HTTP dedicada — más difícil de descubrir para el frontend y de testear; se descarta.
   - *Alternativa B*: eliminar `keep_session` y limitar todo a `SESSION_TIME` — la auditoría recomienda "aplicar `keep_session` solo si el usuario lo solicita", no eliminarlo; se descarta.
   - Elección: campo en el payload validado como booleano; su ausencia equivale a false. **Asunción material**: el frontend actual no envía este campo, así que tras el despliegue todos los logins duran 24 h (comportamiento deseado por F-004).
2. **Vida extendida = `31536000` s (1 año) solo con opt-in**, manteniendo el valor actual para no romper además la expectativa del opt-in. *Alternativa*: reducir también la vida extendida (p. ej. 30 días) — requiere decisión de negocio sobre "recordarme"; queda fuera y no cambia los specs porque el requisito solo exige opt-in explícito.
3. **Revalidación en cada petición autenticada consultando la fila `users`** (una lectura por petición) y **revocación de la sesión activa + rechazo con el error de sesión revocada (901008) cuando `role_id` o `status_id` difieren del token**.
   - *Alternativa A*: aceptar la petición pero usar el rol actual de la BD — no revoca la sesión, deja un token con claims obsoletos siguiendo vivo y no detecta la baja; se descarta (más débil y ambigua).
   - *Alternativa B*: refrescar claims reemitiendo un token — complica el contrato de respuesta del cliente en mitad de la request; se descarta.
   - Elección: revocar y forzar re-login; coincide con el semántica ya existente de sesión revocada/superada (`session-management`).
4. **Alcance de la revalidación**: también en el camino de autenticación oportunista (`ValidateOptional`), porque su `USER_ROLE` decide visibilidad de filas (p. ej. config). Si el rol/estado difiere, ese token no se acepta como autenticado (se comporta como anónimo en vez de fallar la request pública, preservando el contrato de la capacidad `authentication-and-authorization` — "no rechaza la petición por token ausente/inválido").
5. **El coste de la consulta extra por petición es aceptable**: la tabla `users` está indexada por id/PK y ya se consulta en otros caminos; se descarta cachear el rol porque el objetivo es justamente que el cambio surta efecto inmediato.

## Risks / Trade-offs

- [Clientes que dependan de tokens de 1 año pierden sesión al desplegar] → mitigación: marcado **BREAKING** en proposal; el opt-in `keep_session` restaura el comportamiento para quien lo pida; comunicar al frontend.
- [Tokens de 1 año ya emitidos siguen válidos hasta su exp] → mitigación: plan de migración con revocación de sesiones activas en el despliegue (ver Migration Plan); además la revalidación de rol/estado ya los neutraliza si hubo cambios.
- [Consulta extra por petición añade latencia] → mitigación: una lectura puntual por id; medir en el test de integración; sin caché para no diluir la garantía.
- [Desactivar un usuario no bloquea de inmediato el token si la BD no responde] → mitigación: ante error de consulta en la revalidación, fallar cerrado (denegar) en lugar de degradar a confiar en el claim.

## Migration Plan

1. Despliegue del código (sin migración de esquema: no se cambian columnas ni índices).
2. En el despliegue, revocar las sesiones activas existentes (`SessionManager`) para invalidar de facto los tokens de 1 año ya emitidos; los usuarios deberán iniciar sesión de nuevo y recibirán tokens de 24 h.
3. Frontend: enviar `keep_session` solo si implementa "recordarme".
4. Rollback: revertir el commit; los clientes que no enviarían `keep_session` vuelven al comportamiento anterior sin datos residuales.

## Open Questions

- _(ninguno — si negocio decide acortar también la vida del opt-in extendido, basta con cambiar la constante de duración y el escenario asociado; el requisito de opt-in explícito no cambia)_
