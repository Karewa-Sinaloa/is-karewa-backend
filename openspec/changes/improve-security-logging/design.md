# Design

## Context

Ver `proposal.md` — Why (hallazgo F-015 de la auditoría OWASP 2026-10-14). Estado actual relevante:

- `error_logs()` es la superficie única de registro (`openspec/specs/configuration-and-logging`, requisito *Logging*), pero sus fallos acaban en `die()` con la ruta del fichero: `app/core/helpers/log.manager.php:17` (`Cannot open file:  ' . $file`), `:34` (`... can not write to log file ' . $file`) y el mismo patrón en `app/core/helpers/api_response.php:44,54` (`can not write to log file: ' . ERROR_LOG_FILE`).
- Eventos hoy: fallos de login y token (`local_login.php:96`), denegaciones 401/403 (`app/core/auth/module.access.controller.php:57,85`, `app/core/bootstrap/midelware.php:132`), logout con token inválido — pero el rate limit (`ApiResponse::Set('429000')` en `app/core/helpers/rate_limit.php:330`) y las subidas rechazadas no registran nada, y no hay ningún canal de alertado.
- Recuperación de contraseña: `Recovery()` genera un código numérico de 6 dígitos (`mt_rand`), lo guarda con `password_hash`, y `loginMailer()` (`local_login.php:309-319`) descarga la plantilla con `file_get_contents(API_URL . 'mailings/login-recovery?_key=...&code=' . $code . '&recovery_hash=' . base64(json{email,code}) . '&role=...')`; el `code` y el payload descodificable viajan en la query string, y si la plantilla falla se registra la URL íntegra (`local_login.php:319`). `app/core/modules/mailings/index.php` + `login_recovery.php` construyen el enlace `SITE_URL|CMS_URL/access/reset?h=<recovery_hash>` que el frontend decodifica para hacer `POST /access/reset {email, code, password}` (`Reset()` valida `code => required|numeric|min_value:100000|max_value:999999` en `local_login.php:234-238`).
- Sin infraestructura de alertado ni correlación: no hay CI (`.github/workflows` no existe), ni SIEM, ni métricas; solo ficheros de log en `logs/`.
- Solape de alcance: los permisos de `logs/` (0777) son del cambio `restrict-file-permissions` (F-011); este cambio no los toca.

## Goals / Non-Goals

**Goals:**

- Que cada evento de seguridad del conjunto definido deje una trama correlacionable y sin valores secretos.
- Que ningún secreto viaje en una query string; el enlace de recuperación usa solo el hash de un solo uso.
- Que un fallo de escritura de log sea *best effort*: no altera la respuesta del cliente ni revela rutas.

**Non-Goals:**

- Alertado, notificaciones, dashboards o correlación automática: no existe infraestructura que los consuma; solo se especifica la emisión de eventos (decisión 2).
- Permisos de ficheros y directorios de log (F-011 → cambio `restrict-file-permissions`).
- Rotación/retención/volumen de logs y contenido histórico ya escrito.
- Cambiar el mecanismo de persistencia de logs (sigue siendo fichero bajo `logs/`).

## Decisions

1. **Conjunto de eventos de seguridad**. Se emiten: fallos de autenticación (credenciales inválidas, token ausente/inválido/expirado), denegaciones de autorización (rol no permitido o fila no editable por el rol = escalada bloqueada), rechazos por límite de tasa (429), subidas rechazadas por validación o política, y métodos/peticiones inválidos.
   - *Alternativa A*: auditoría completa de toda operación de escritura — descartada: volumen alto, ningún consumidor, y la auditoría pidió solo fuerza bruta, escaladas y subidas anónimas.
   - *Alternativa B*: solo 401/403 (los que ya existen) — descartada: deja sin cubrir 429 y subidas, dos de los tres ejemplos del hallazgo.
   - *Elección*: el conjunto de arriba, con contexto mínimo (timestamp, módulo/componente, código/outcome, identidad cuando se conoce). **Asunción material**: la identidad se emite solo cuando existe (peticiones anónimas se correlacionan por IP de cliente ya presente en el entorno de la petición, no se propaga al mensaje).

2. **Alertado y correlación: fuera de alcance (Non-Goal), se emite solo el evento**.
   - *Alternativa A*: especificar alertas sobre picos de 401/403/429 (recomendación literal de la auditoría) — descartada por ahora: no hay sistema de métricas, canal de notificación ni CI que los consuma; especificarlos crearía requisitos incumplibles hoy.
   - *Alternativa B*: un job que lance señales cuando el fichero de log crezca por encima de umbral — descartada: otro componente operativo sin destinatario.
   - *Elección*: la capacidad `configuration-and-logging` gana requisitos de emisión; el alertado queda como futura capacidad cuando exista infraestructura. **Asunción material**: la auditoría se podrá cerrar para F-015 (emisión) sin cubrir alertado, que queda documentado aquí.

3. **Protocolo de recuperación: el código de 6 dígitos sale de las URLs y pasa a hash de un solo uso**. `Recovery()` genera un token opaco de un solo uso (`random_bytes`), guarda `recovery_code = password_hash(token)`, y el enlace (y la URL interna de mailings) llevan `recovery_hash=<token>` + `role` + `email`; se elimina `code=` y el payload base64. `Reset()` acepta ese hash en el campo `code` (la regla de validación pasa de `numeric` de 6 dígitos a cadena opaca acotada en longitud) y lo verifica con el mismo `password_verify` contra `recovery_code`; el éxito limpia `recovery_code`/`recovery_date` → uso único, con la misma ventana de expiración que hoy.
   - *Alternativa A*: mantener el payload base64 `{email, code}` — descartada: es exactamente el descodificable que prohíbe el hallazgo.
   - *Alternativa B*: cifrar el código en el enlace (AES-GCM) — descartada: introduce gestión de claves en el enlace y el frontend no podría recuperar el código sin el servidor, con lo que el contrato tendría que cambiar igualmente.
   - *Alternativa C*: conservar el código de 6 dígitos y añadir una columna para el hash — descartada: cambio de esquema innecesario; `recovery_code` ya almacena un `password_hash` verificable.
   - *Elección*: C con token en lugar de código (sin cambio de esquema, misma verificación, UX de enlace puro — la plantilla nunca mostraba el código). El correo, no la URL, es el canal de entrega del enlace.
   - **Asunción material**: el frontend debe leer `h`/`recovery_hash` y `email` como parámetros planos en vez de decodificar base64; se coordina el despliegue (ver Riesgos).

4. **Fallos de logging: *best effort* con degradación a canal interno**. `error_logs()` nunca termina la petición: si el fichero no es escribible, degrada a `error_log()` (stderr del contenedor → `docker logs`, ya usado como canal operativo por `make logs`) y la respuesta del cliente sigue la suya. Los tres `die()` con ruta (`log.manager.php:17,34`, `api_response.php:44,54`) se sustituyen por ese comportamiento.
   - *Alternativa A*: mantener `die()` con mensaje — descartada: mata la petición y filtra la ruta (el hallazgo literal).
   - *Alternativa B*: convertirlo en `AppException` con código de la tabla — descartada: un fallo de log no debe reescribir el código de respuesta de la operación que el cliente pidió (p. ej. un login válido devolvería un error distinto).
   - *Elección*: la respuesta original se preserva; el problema de logging sale por stderr sin ruta al cliente.

5. **Solape con F-011 (permisos) referenciado, no especificado**. Este cambio no toca `mkdir`/`chmod` de `log.manager.php:15-19`; el cambio `restrict-file-permissions` es el dueño de 0750/0640. Aquí solo se exige que un fallo de escritura por permisos no rompa la petición (decisión 4).

6. **Dónde se emiten los eventos**: en los puntos donde ya se decide la denegación (controlador de acceso, middleware de rol, rate limit, subidas), reutilizando la superficie de registro existente; no se crea un subsistema de auditoría paralelo. *Alternativa descartada*: un módulo `audit` con su propia tabla — más de lo que pide el hallazgo y sin consumidor.

## Risks / Trade-offs

- [Los cambios `fix-login-recovery-xss` y `harden-recovery-code` validan/round-tripan la URL de `mailings/login-recovery` con `code=` y `recovery_hash` en base64 → sus escenarios y validaciones quedarían obsoletos] → Coordinar antes de implementar: actualizar los escenarios de `fix-login-recovery-xss` (su diseño ya declara que si cambia el emisor hay que actualizar la validación) y confirmar que el límite de intentos de `harden-recovery-code` aplica igual al hash opaco (cuenta igual: `password_verify` fallido).
- [El frontend (repo Vue separado) decodifica `h` como base64 `{email, code}` → enlaces viejos y parsing viejo rompen el reset] → **BREAKING** ya marcado en `proposal.md`; despliegue coordinado frontend+backend, y los enlaces emitidos antes del deploy dejan de valer (el usuario pide uno nuevo; el token anterior ya estaba de por sí limitado por `recovery_date`).
- [Los enlaces de recuperación emitidos durante la ventana de despliegue con el formato antiguo dejan de funcionar] → Ventana corta aceptada; alternativa descartada: mantener compatibilidad dual (aceptar base64 además del hash) — prolonga exactamente la fuga que se cierra.
- [Emitir más eventos aumenta el volumen de log y el I/O en el mismo fichero] → Volumen acotado (solo denegaciones/rechazos, no tráfico normal); sin rotación propia (fuera de alcance, decisión de Non-Goals).
- [Suprimir el `die()` puede ocultar fallos de logging a quien solo mira las respuestas HTTP] → Degradación a `error_log()` (stderr) mantiene la señal en el canal operativo `make logs`.
- [Si el frontend no se despliega a la vez, el reset falla en el enlace] → checklist de despliegue en `tasks.md` con prueba manual del flujo completo de recuperación.
- [Tests que hoy fijan la regla `code` numérica de 6 dígitos (p. ej. mapeo de campos de `access`) fallen al cambiar la regla] → localizar y actualizar esos tests en el mismo grupo de tareas (ver `tasks.md`).

## Migration Plan

1. Backend primero: emitir eventos, hacer *best effort* el logging, cambiar el protocolo de recuperación (acepta el hash de un solo uso).
2. Frontend (repo Vue) en el mismo despliegue: leer `h` + `email` planos, enviar `POST /access/reset {email, code: <hash>, password}`.
3. Actualizar los escenarios de los cambios hermanos afectados (`fix-login-recovery-xss`, `harden-recovery-code`).
4. Verificación: `./vendor/bin/phpunit` completo, prueba manual del flujo `POST /access/recovery` → correo → enlace → `POST /access/reset`, y búsqueda de secretos en `logs/`.
5. *Rollback*: revert del commit; el backend anterior vuelve a emitir/enlazar con el formato base64 y el frontend anterior lo consume (no hay estado de datos que revertir salvo `recovery_code`, cuyo formato no cambia: sigue siendo `password_hash`).

## Open Questions

Ninguna que afecte a specs, enfoque o desglose de tareas. Temas diferidos a futuros cambios sin impacto en este plan: alertado sobre picos de 401/403/429 (decisión 2) y rotación/retención de los ficheros de log (fuera de alcance declarado).
