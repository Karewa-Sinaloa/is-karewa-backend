# Tasks

## 1. Fuente única de la política

- [ ] 1.1 Definir la política de contraseñas (mínimo 10 caracteres + lista de contraseñas comunes) en una única fuente compartida por `users` y `access`, con un test unitario que fija la longitud mínima declarada — `./vendor/bin/phpunit --filter PasswordPolicyTest`
- [ ] 1.2 Añadir un test que recorre los tres puntos de fijado (`store`, `update`, `Reset`) y afirma que todos usan la misma política, de modo que volver a divergir (hoy `min:6` vs `min:8`) rompa la suite — `./vendor/bin/phpunit --filter PasswordPolicyTest`

## 2. Aplicación por flujo

- [ ] 2.1 Aplicar la política en el alta de usuario (`store`) y test: crear con contraseña de 8 caracteres → error `400000` y no se crea el usuario — `./vendor/bin/phpunit --filter PasswordPolicyTest`
- [ ] 2.2 Aplicar la política en la actualización (`update`) solo cuando el payload lleva contraseña, y tests: `PUT` con contraseña de 6 caracteres → `400000` y el hash almacenado sigue verificando contra el valor anterior; `PUT` sin contraseña → la actualización procede y el hash no cambia — `./vendor/bin/phpunit --filter PasswordPolicyTest`
- [ ] 2.3 Aplicar la política en el restablecimiento (`Reset`) y test: reset con contraseña de 9 caracteres → `400000` y la contraseña almacenada queda intacta — `./vendor/bin/phpunit --filter PasswordPolicyTest`

## 3. Bloqueo de contraseñas comunes

- [ ] 3.1 Añadir el recurso de lista curada de contraseñas comunes con el criterio de curación documentado junto al recurso, y verificar con `grep -rn "comunes\|common" <recurso y su doc>` que la documentación quedó escrita
- [ ] 3.2 Rechazar contraseñas de la lista en los tres flujos y test: contraseña de 10+ caracteres tomada de la lista → `400000`; contraseña de 10+ caracteres fuera de la lista → aceptada — `./vendor/bin/phpunit --filter PasswordPolicyTest`

## 4. Credenciales existentes

- [ ] 4.1 Mantener la verificación de login sin la política de creación y test: autenticar con una contraseña almacenada de menos de 10 caracteres (fixture creada antes de la política) → la autenticación tiene éxito — `./vendor/bin/phpunit --filter PasswordPolicyTest`

## 5. Verificación de integración

- [ ] 5.1 Ejecutar la suite completa `./vendor/bin/phpunit` y verificar que pasa sin regresiones (incluye actualizar expectativas de tests existentes que asumían `min:8`/`min:6`, si los hubiera)
- [ ] 5.2 Verificar con `openspec validate strengthen-password-policy` que el cambio es válido
