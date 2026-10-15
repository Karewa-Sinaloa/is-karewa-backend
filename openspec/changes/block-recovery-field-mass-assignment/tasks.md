# Tasks

## 1. Bloqueo de escritura en el módulo users

- [ ] 1.1 Añadir `'saved' => false` a `recovery_code` y `recovery_date` en `$moduleFields` (app/core/modules/users/controller.php:28-29), siguiendo el patrón de `hcaptcha_bypass`; verificar con `testCreatePayloadWithRecoveryFieldsIsIgnored` (crear usuario enviando ambos campos → columnas sin valor en BD y respuesta de éxito normal) en `./vendor/bin/phpunit --filter RecoveryField`.
- [ ] 1.2 Añadir `testUpdatePayloadWithRecoveryFieldsIsIgnored`: tras emitir un código por `access`, un `update` de `users` con `recovery_code`/`recovery_date` no altera los valores guardados; verificación: `./vendor/bin/phpunit --filter RecoveryField`.
- [ ] 1.3 Añadir `testPlaintextRecoveryCodeIsNeverPersisted` (texto plano en payload → nada persistido y ningún campo de recuperación en la respuesta); verificación: `./vendor/bin/phpunit --filter RecoveryField`.
- [ ] 1.4 Revisar si `api/docs/openapi.json` documenta `recovery_code`/`recovery_date` como campos enviables de los endpoints de `users` y corregirlo si procede; verificación: `./vendor/bin/phpunit --filter OpenApiSyncTest` pasa.

## 2. Único escritor y valor hasheado

- [ ] 2.1 Añadir `testRecoveryFlowIsTheOnlyWriter`: el flujo `access` sigue fijando `recovery_code`/`recovery_date` al pedir recuperación (escenario «Recovery writes the recovery-date column» intacto); verificación: `./vendor/bin/phpunit --filter RecoveryField`.
- [ ] 2.2 Añadir `testStoredRecoveryCodeIsNotTheIssuedCode`: el valor almacenado difiere del código emitido y no lo revela (hash de un solo sentido); verificación: `./vendor/bin/phpunit --filter RecoveryField`.

## 3. Integración

- [ ] 3.1 Ejecutar la suite completa `./vendor/bin/phpunit` y verificar que pasa (en particular `UsersFieldMappingTest`, `AccessFieldMappingTest`, `OpenApiSyncTest`).
- [ ] 3.2 Ejecutar `openspec validate "block-recovery-field-mass-assignment"` y verificar que el cambio es válido.
