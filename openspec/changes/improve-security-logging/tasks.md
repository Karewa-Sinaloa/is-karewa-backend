# Tasks

## 1. Registro de eventos de seguridad

- [ ] 1.1 Emitir evento de fallo de autenticación con contexto correlacionable en los dos caminos existentes: credenciales inválidas en login y token ausente/inválido/expirado; verificar con `./vendor/bin/phpunit tests/SecurityEventLoggingTest.php` (nuevo test: cada fallo produce una entrada con módulo, código/outcome y la identidad cuando existe)
- [ ] 1.2 Emitir evento de denegación de autorización en los puntos que hoy responden 403 (rol no permitido / fila no editable por rol) incluyendo la identidad del actor; verificar con un caso nuevo en `tests/SecurityEventLoggingTest.php` que la entrada de la denegación queda escrita con módulo, outcome e identidad
- [ ] 1.3 Emitir evento cuando el límite de tasa rechaza una petición (respuesta 429, hoy sin registrar); verificar con un caso nuevo en `tests/SecurityEventLoggingTest.php` apoyado en el harness de rate limit existente (`tests/RateLimitEnforcementTest.php` como referencia) que la entrada aparece al producirse el rechazo
- [ ] 1.4 Emitir evento cuando una subida es rechazada por validación o política (image-upload y attachments) y cuando llega un método/petición inválida; verificar con casos nuevos en `tests/SecurityEventLoggingTest.php` que ambos rechazos dejan entrada
- [ ] 1.5 Documentar el conjunto de eventos y el formato de correlación en `SECURITY.md` (sección nueva de registro de seguridad); verificar con `grep -E "autenticación|autorización|límite de tasa|subida|método" SECURITY.md` que las cinco categorías quedan descritas

## 2. Recuperación sin secretos en URLs

- [ ] 2.1 Generar en `Recovery()` un token opaco de un solo uso con `random_bytes` y almacenarlo con `password_hash` en la columna existente `recovery_code` (sin cambio de esquema, sin código de 6 dígitos); verificar con `./vendor/bin/phpunit tests/RecoveryTokenTest.php` (nuevo test: el valor almacenado no es un código numérico de 6 dígitos y `password_verify` del token emitido contra el valor almacenado devuelve verdadero)
- [ ] 2.2 Cambiar `loginMailer()` para que la URL de `mailings/login-recovery` lleve solo `recovery_hash=<token>`, `role` y `email` — sin `code=` ni payload base64; verificar con un caso en `tests/RecoveryTokenTest.php` que la URL generada no contiene el parámetro `code`, no contiene base64 descodificable de `{email, code}` y contiene el hash de un solo uso
- [ ] 2.3 Adaptar `app/core/modules/mailings/` (índice y `login_recovery.php`) a los parámetros planos y construir el enlace `.../access/reset?h=<token>&email=<email>`; verificar con un test de round-trip (recuperar plantilla → extraer el enlace → comprobar parámetros) que el enlace contiene el token y el correo y ningún código
- [ ] 2.4 Adaptar `Reset()`: el campo `code` acepta el hash opaco (regla de validación acotada en longitud en lugar de `numeric` de 6 dígitos), se verifica con `password_verify` contra `recovery_code`, y el éxito limpia `recovery_code`/`recovery_date` (uso único y expiración intactos); verificar con `./vendor/bin/phpunit tests/RecoveryResetContractTest.php` (nuevo test: hash válido + password nuevo acepta; hash ya consumido o expirado rechaza con el código de expiración existente) y con `./vendor/bin/phpunit tests/AccessFieldMappingTest.php` que los tests de mapeo siguen pasando
- [ ] 2.5 Dejar registrado en `SECURITY.md` el formato nuevo del enlace de recuperación y la nota de coordinación: el frontend (repo Vue) debe leer `h` y `email` planos, y los cambios `fix-login-recovery-xss` y `harden-recovery-code` deben actualizar sus propios escenarios (no se editan artefactos de otros cambios aquí); verificar con `grep -n "h=\|coordina" SECURITY.md` la presencia de la sección
- [ ] 2.6 Eliminar valores secretos de los mensajes de log del flujo de recuperación (el hash de credencial registrado al fallar el guardado y la URL íntegra registrada al fallar la plantilla); verificar con un caso nuevo en `tests/SecurityEventLoggingTest.php` que las entradas de esos dos fallos no contienen el hash de credencial ni ninguna URL con credencial

## 3. Fallos de logging sin ruptura ni rutas

- [ ] 3.1 Hacer que `app/core/helpers/log.manager.php` no termine la petición: retirar el `die()` de apertura y la respuesta 500 con ruta al cliente, degradando la escritura fallida a `error_log()` (stderr del contenedor) y devolviendo éxito/fallo a quien lo solicite; verificar con `./vendor/bin/phpunit tests/LoggingResilienceTest.php` (nuevo test: apuntando el registro a una ruta no escribible, la llamada no termina el proceso ni emite salida, y el fallo se reporta)
- [ ] 3.2 Retirar los dos `die()` con `ERROR_LOG_FILE` en `app/core/helpers/api_response.php` siguiendo el mismo comportamiento; verificar con un caso en `tests/LoggingResilienceTest.php` que una ruta de log no escribible no produce salida con ruta al cliente y con `grep -rn "write to log" app/core/helpers/` que ya no queda el mensaje con ruta
- [ ] 3.3 Añadir el test de barrido de secretos: ejecutar los flujos de login fallido, recuperación y subida rechazada en los tests y examinar las entradas producidas en busca de material de credenciales (contraseñas, tokens JWT, hashes `password_hash`, código/hash de recuperación); verificar con `./vendor/bin/phpunit tests/SecurityEventLoggingTest.php --filter secret` que el barrido pasa
- [ ] 3.4 Documentar en `SECURITY.md` el comportamiento *best effort* del registro (la respuesta del cliente no cambia ni contiene rutas; el fallo interno va a stderr/`make logs`); verificar con `grep -n "stderr\|best effort\|make logs" SECURITY.md`

## 4. Verificación de integración

- [ ] 4.1 Ejecutar la suite completa `./vendor/bin/phpunit` desde la raíz del repo y confirmar que termina sin errores
- [ ] 4.2 Prueba de humo del flujo completo de recuperación (solicitud → URL interna sin secretos → enlace con hash de un solo uso → reset con hash → segundo uso del mismo enlace rechazado) y revisión de que `logs/` no contiene credenciales tras el flujo — comando `! grep -RniE "recovery_hash=[A-Za-z0-9+/=]{8,}|code=[0-9]{6}|\\$2y\\$" logs/` (termina en 0 solo si no hay coincidencias)
- [ ] 4.3 `openspec validate "improve-security-logging"` devuelve válido
