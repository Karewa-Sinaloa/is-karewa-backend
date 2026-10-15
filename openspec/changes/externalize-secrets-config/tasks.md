# Tasks

## 1. Overlay de variables de entorno para secretos

- [ ] 1.1 Implementar el overlay de secretos (patrón de `app/core/config/mail_env.php`) para `database.password`, `mailing.password`, `hcaptcha.secret` y `hash`, con prelación entorno > archivo revelado, y verificar con test unitario nuevo (estilo `tests/MailConfigEnvTest.php`) que una variable definida tiene prioridad sobre el valor del archivo — `./vendor/bin/phpunit --filter <nombre-del-test>`
- [ ] 1.2 Verificar con test que una variable ausente deja intacto el valor del archivo revelado — `./vendor/bin/phpunit --filter <nombre-del-test>`
- [ ] 1.3 Verificar con test que el uso de un secreto no disponible (sin variable ni valor revelado, solo placeholder) produce un error de configuración y nunca un valor por defecto — `./vendor/bin/phpunit --filter <nombre-del-test>`

## 2. Configuración sin valores reales

- [ ] 2.1 Sustituir los cuatro secretos de `app/config.yml` por placeholders y regenerar `app/config.yml.secret` vía git-secret; verificar con `grep` de los valores antiguos sobre `app/config.yml` y el repositorio que no aparecen (cada valor sustituido deja de coincidir) — comando de verificación documentado en la tarea
- [ ] 2.2 Documentar `SENDGRID_API_KEY`, `HCAPTCHA_SECRET`, `HASH_AUTH_SECRET` y `DB_PASSWORD` en `.env.example` con placeholders, y verificar con test que el mapa de variables documentado coincide con las que la aplicación lee — `./vendor/bin/phpunit --filter <nombre-del-test>`
- [ ] 2.3 Verificar con el stack local que la aplicación arranca y opera con las variables definidas en `.env` — `make up` y comprobación de login + envío de correo + verificación hCaptcha

## 3. Rotación de credenciales expuestas (operaciones)

- [ ] 3.1 Rotar en sus sistemas de origen las credenciales expuestas (BD incluida la de root de `.env`, API key de SendGrid, secreto hCaptcha, secreto HMAC de hash-auth) y actualizar las variables de entorno; verificación: los valores antiguos dejan de autenticar (login/envío/verificación con el valor viejo falla) y los nuevos funcionan — prueba manual registrada en la tarea
- [ ] 3.2 Regenerar el archivo revelado `app/config.yml.secret` con los valores ya rotados y verificar que el reveal de `setup.sh` produce una configuración válida — ejecución de `setup.sh` (o revelado manual) sin errores

## 4. Verificación de integración

- [ ] 4.1 Ejecutar la suite completa `./vendor/bin/phpunit` y verificar que pasa sin regresiones
- [ ] 4.2 Verificar con `openspec validate externalize-secrets-config` que el cambio es válido
