# Tasks

## 1. Generación criptográfica del código

- [ ] 1.1 Sustituir `mt_rand(100000, 999999)` por `random_int(100000, 999999)` en `Recovery()` (app/core/modules/access/local_login.php:170) y verificar con `./vendor/bin/phpunit --filter RecoveryCode` que la nueva prueba `testRecoveryCodeGenerationUsesSecureRandomSource` (contiene `random_int` y no `mt_rand`/`rand`) pasa.
- [ ] 1.2 Añadir la prueba `testConsecutiveRecoveryRequestsIssueDifferentCodes`: dos recuperaciones consecutivas para la misma cuenta generan códigos distintos; verificación: `./vendor/bin/phpunit --filter RecoveryCode`.

## 2. Límite de intentos fallidos

- [ ] 2.1 Crear la migración `resources/migrations/recovery_attempts.sql` con la tabla `recovery_attempts` (`user_id` UNIQUE, contador, marca temporal) y verificar que se aplica limpiamente en SQLite en los tests (patrón de `user_sessions`) y en MySQL dev.
- [ ] 2.2 Implementar el contador: incrementar en cada verificación fallida de `Reset()`, reiniciar al emitir un código nuevo, y al llegar a 5 limpiar `recovery_code`/`recovery_date` (misma invalidación que el uso exitoso); verificación con `testFiveFailedAttemptsInvalidateTheCode`: tras 5 códigos erróneos, el código correcto se rechaza con 901005.
- [ ] 2.3 Añadir `testCorrectCodeWithinAttemptLimitStillWorks` (2 fallos + código correcto → reset OK) y `testRequestingNewCodeResetsAttemptCount` (alcanzar el límite, pedir código nuevo, usarlo → OK); ambas pasan con `./vendor/bin/phpunit --filter RecoveryCode`.
- [ ] 2.4 Verificar que ningún fallo añade códigos de respuesta nuevos: prueba `testFailedAttemptsUseTheExistingInvalidCodeError` que aserta solo 901005/901006/404000 en todo el flujo; verificación: `./vendor/bin/phpunit --filter RecoveryCode`.

## 3. Uso único y TTL

- [ ] 3.1 Añadir `testReuseAfterSuccessfulResetIsRejected`: tras un reset exitoso, reenviar el mismo código → 901005; verificación: `./vendor/bin/phpunit --filter RecoveryCode`.
- [ ] 3.2 Añadir `testNewRecoveryRequestReplacesPreviousCode` (el código anterior queda inválido tras emitir uno nuevo) y `testExpiredCodeRejectedKeepsTTL3600` (2999 s → válido, 3601 s → 901006); verificación: `./vendor/bin/phpunit --filter RecoveryCode`.

## 4. Integración

- [ ] 4.1 Ejecutar la suite completa `./vendor/bin/phpunit` y verificar que todos los tests pasan (especialmente `AccessLogoutTest`, `RateLimitTest`, `OpenApiSyncTest`).
- [ ] 4.2 Ejecutar `openspec validate "harden-recovery-code"` y verificar que el cambio es válido.
