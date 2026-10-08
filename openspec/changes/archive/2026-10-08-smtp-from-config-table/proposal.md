# Proposal

## Why

Las credenciales SMTP viven solo en `app/config.yml` (gitignored), así que cada despliegue necesita acceso al archivo para rotar credenciales o cambiar de proveedor, y una fila ya existente en la tabla `config` (`slug = smtp_config`) guarda exactamente esa configuración sin que nada la lea. Mover la fuente a la base de datos permite editar el SMTP desde la API (`PUT /api/v5/config/{id}`) y elimina los secretos del archivo de configuración.

## What Changes

- El transporte de correo se configura a partir de la fila `config` con `slug = smtp_config`, leída por la carga existente de `$_apiConfig` en el arranque, en lugar de la sección `mailing` de `app/config.yml`.
- Se define el mapeo de las claves que la fila ya usa: `host` → host, `port` → port, `security` → security, `user` → user, `pass` → password, `from` → from_email; `smtp_auth` se deriva de `user` y `pass`, `debug` queda en `0` y `from_name` queda vacío, porque la fila no trae esas claves.
- La precedencia queda `MAIL_*` (entorno) > fila `smtp_config` (BD) > `app/config.yml` (archivo), conservando el overlay de entorno que usa Mailpit en local.
- Si la fila no existe o su `value` no es JSON válido, se usan los valores de `app/config.yml` y se registra el problema con `error_logs()`, para que un despliegue sin la fila no rompa el envío.
- **BREAKING**: ninguna para la API pública; es un cambio de fuente de configuración. Un despliegue que dependa del nombre de remitente (`from_name`) debe fijarlo con `MAIL_FROM_NAME`, porque la fila no lo provee.

## Capabilities

### New Capabilities

- Ninguna.

### Modified Capabilities

- `mail-delivery`: el requisito que hoy dice que el transporte sale de la "sección de configuración" pasa a exigir que salga de la fila `smtp_config` de la tabla `config`, con el mapeo de claves, la derivación de `smtp_auth`, el respaldo al archivo cuando la fila falta o no es JSON, y la prelación frente a las variables de entorno.
- `configuration-and-logging`: el requisito de override por variables de entorno, que hoy declara que el valor base y el respaldo son los de `app/config.yml`, pasa a declarar la prelación entorno > fila `smtp_config` > archivo.

## Impact

- `app/core/helpers/phpmailer.php` — resolver las settings del transporte desde `$_apiConfig->smtp_config` antes de armar PHPMailer.
- `app/core/config/base.php` y `app/core/config/mail_env.php` — el overlay de entorno debe aplicarse después de mezclar la fila, para que `MAIL_*` siga ganando.
- `app/core/bootstrap/init.php` — punto de cableado: la fila ya está cargada cuando `helpers/api_configuration.php` se incluye.
- `app/core/modules/access/local_login.php` — lee `$_config->mailing` para armar el remitente; debe seguir leyendo el objeto ya resuelto.
- Tests — `tests/MailerTest.php` y `tests/MailConfigEnvTest.php` cubren hoy solo el archivo y el overlay; se agregan casos para fila presente, fila ausente/JSON inválido y prelación.
- Tabla `config` — sin cambios de esquema; se reutiliza la fila `smtp_config` y la API de config ya existente.
