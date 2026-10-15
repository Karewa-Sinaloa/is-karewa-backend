# Tasks

## 1. Respuesta uniforme de recuperación

- [ ] 1.1 Eliminar la rama `404000` de `Recovery()` (app/core/modules/access/local_login.php:160-162) y responder siempre el mismo éxito simulado; verificar con `testUnknownAndKnownRecoveryResponsesAreIdentical` (compara código, mensaje y campos de las dos respuestas byte a byte) en `./vendor/bin/phpunit --filter AccountEnumeration`.
- [ ] 1.2 Añadir `testInactiveAccountGetsTheSameResponseAndNoMail`: cuenta inactiva → misma respuesta que cuenta activa y sin actualización de `recovery_code`/`recovery_date` en BD (fila sin cambios); verificación: `./vendor/bin/phpunit --filter AccountEnumeration`.
- [ ] 1.3 Añadir `testValidationErrorsDoNotDependOnExistence`: payload inválido (email sin `@`) → mismos errores de validación para correo desconocido y conocido; verificación: `./vendor/bin/phpunit --filter AccountEnumeration`.

## 2. Igualación temporal de recuperación

- [ ] 2.1 Implementar el mínimo de tiempo de respuesta (constante 500 ms en el módulo `access`) aplicado a todas las ramas de `Recovery()` y la verificación simulada de coste equivalente en la rama de cuenta inexistente; verificar con `testUnknownEmailIsNotAnsweredFaster` (cronometra la respuesta desconocida y aserta duración ≥ 500 ms) en `./vendor/bin/phpunit --filter AccountEnumeration`.
- [ ] 2.2 Añadir `testKnownEmailObservesTheSameMinimum`: cronometra la respuesta para cuenta activa y asierte duración ≥ 500 ms; verificación: `./vendor/bin/phpunit --filter AccountEnumeration`.
- [ ] 2.3 Documentar en `docs/auditoria-seguridad/` o en el changelog del despliegue el piso de 500 ms y cómo ajustarlo si la latencia de envío lo supera; verificación: el texto queda escrito y su comando/enlace citado en esta tarea.

## 3. Igualación temporal y respuestas de login

- [ ] 3.1 Añadir el hash fijo (dummy) con algoritmo/coste de `PASSWORD_DEFAULT` y ejecutar `password_verify` contra él en la rama de usuario inexistente de `Login()`; verificar con `testLoginForUnknownEmailTakesEquivalentTime` (medida desconocido vs. existente+contraseña errónea, diferencia dentro de tolerancia) en `./vendor/bin/phpunit --filter AccountEnumeration`.
- [ ] 3.2 Añadir `testDummyHashMatchesApplicationHashCost`: el hash fijo verifica con el mismo algoritmo/coste que un hash generado por la aplicación; verificación: `./vendor/bin/phpunit --filter AccountEnumeration`.
- [ ] 3.3 Añadir `testWrongPasswordResponsesAreIdenticalForUnknownAndExistingAccounts` (mismo 901004, mensaje y campos) y `testLoginForExistingAccountStillSucceeds`; verificación: `./vendor/bin/phpunit --filter AccountEnumeration`.

## 4. Integración

- [ ] 4.1 Ejecutar la suite completa `./vendor/bin/phpunit` y verificar que pasa (ninguna prueba existente dependía del `404000` de `Recovery`).
- [ ] 4.2 Ejecutar `openspec validate "prevent-account-enumeration"` y verificar que el cambio es válido.
