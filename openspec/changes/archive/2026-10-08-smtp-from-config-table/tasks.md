# Tasks

## 1. Resolución de las settings de correo

- [x] 1.1 Implementar `apply_smtp_config_row()` en `app/core/config/mail_env.php`: mapea `host`, `port` (entero), `security`, `user`, `pass` → `password`, `from` → `from_email`; deriva `smtp_auth` de `user` y `pass` no vacíos; fija `debug = 0` y `from_name = ''`; si `$value` es `null` o no decodifica a objeto devuelve el objeto intacto. Crear `tests/MailSmtpRowTest.php` con los casos de mapeo de una fila con el shape real y de fila ausente/JSON inválido. Verificar con `php -l app/core/config/mail_env.php` y `./vendor/bin/phpunit tests/MailSmtpRowTest.php`.
- [x] 1.2 Implementar `resolve_mailing_settings()` en el mismo archivo: capa de archivo (`$_config->mailing`) → fila (`$_apiConfig->smtp_config`) → overlay `MAIL_*`, con `$env` y `$log` inyectables. Añadir a `tests/MailSmtpRowTest.php` los casos de prelación: fila gana al archivo, entorno gana a la fila, fila ausente y JSON inválido caen al archivo invocando `$log`. Verificar con `./vendor/bin/phpunit tests/MailSmtpRowTest.php`.

## 2. Consumidores y capa de archivo

- [x] 2.1 Quitar el overlay eagerly de `app/core/config/base.php` (líneas 40-42) para que `$_config->mailing` sea solo la capa de archivo, dejando comentario que apunte a `resolve_mailing_settings()`. Verificar con `php -l app/core/config/base.php` y `./vendor/bin/phpunit tests/CorsHeadersTest.php tests/ApiUrlTest.php`.
- [x] 2.2 Hacer que `ApiMailer::Send()` en `app/core/helpers/phpmailer.php` obtenga las settings con `resolve_mailing_settings()` en lugar de leer `$_config->mailing`. Verificar con `./vendor/bin/phpunit tests/MailerTest.php` y con el caso nuevo que afirma que el transporte toma host, puerto, usuario, contraseña, seguridad y autenticación de la fila `smtp_config` cuando `$_apiConfig->smtp_config` está presente.
- [x] 2.3 Cambiar `app/core/modules/access/local_login.php:309` para armar el remitente con `resolve_mailing_settings()`. Verificar con `php -l app/core/modules/access/local_login.php` y con `grep -n "_config->mailing" app/core/helpers/phpmailer.php app/core/modules/access/local_login.php` que no queden lecturas directas.
- [x] 2.4 Actualizar `README.md` (líneas 107 y 129-132) y el comentario de `.env.example` sobre el destino de correo: la fuente es la fila `smtp_config` de la tabla `config`, con prelación `MAIL_*` > fila > `app/config.yml`. Verificar con `grep -n "smtp_config" README.md .env.example`.

## 3. Verificación de la especificación

- [x] 3.1 Añadir el caso "fila actualizada entre dos envíos" a `tests/MailerTest.php`: cambiar `$_apiConfig->smtp_config` entre dos llamadas a `ApiMailer::Send()` y afirmar que el segundo transporte usa el host nuevo sin reiniciar. Verificar con `./vendor/bin/phpunit tests/MailerTest.php`.
- [x] 3.2 Correr la suite completa `./vendor/bin/phpunit` y confirmar 0 fallos (los 2 warnings y 2 deprecations preexistentes de `update.php`/`fields.php` se mantienen).
