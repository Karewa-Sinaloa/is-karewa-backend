# Proposal

## Why

La auditoría OWASP 2026-10-14 confirma el hallazgo **F-002 (Alto)**: `app/config.yml` está gitignored, pero vive en claro en el árbol de trabajo con valores reales —credenciales de BD (`database.password`, app/config.yml:26), API key de SendGrid (`mailing.password`, :99), secreto HMAC de hash-auth (`hash`, :120, consumido como `HASH_AUTH_PASS` en app/core/config/base.php:188) y secreto hCaptcha (`hcaptcha.secret`, :124, usado en app/core/helpers/hcaptcha.php:47)—, y `.env` contiene además credenciales de BD incluida la de root. El flujo `git-secret` existe (`app/config.yml.secret`, revelado por `setup.sh`) y el patrón de overlay por variables de entorno ya se usa para correo (`app/core/config/mail_env.php`), pero la configuración efectivamente en uso no aprovecha ninguno de los dos de forma segura.

## What Changes

- Los secretos de ejecución (credenciales de BD, API key de SendGrid, secreto hCaptcha, secreto HMAC de hash-auth) se sirven desde variables de entorno siguiendo el patrón overlay de `mail_env.php`, con el flujo `git-secret` como respaldo soportado; la prelación es entorno > archivo revelado.
- **BREAKING**: ningún archivo de configuración en el árbol de trabajo ni trackeado en el repositorio contiene valores secretos reales; sin variable definida ni archivo revelado, la operación que requiere el secreto falla en lugar de usar un valor por defecto.
- Rotación de las credenciales expuestas (BD, SendGrid, hCaptcha, HMAC) como paso de despliegue/operaciones, con verificación (no hay código de rotación).

## Capabilities

### New Capabilities

- _(ninguna)_

### Modified Capabilities

- `configuration-and-logging`: se añaden requisitos de externalización de secretos de ejecución (variables de entorno o git-secret) y de ausencia de valores secretos reales en el árbol de trabajo y en el repositorio.

## Impact

- Código: `app/core/config/base.php` (carga de `database`, `hcaptcha`, `hash`; `MYSQL_PSWD` :130), patrón overlay de `app/core/config/mail_env.php`, `app/config.yml` (secretos sustituidos por placeholders).
- Despliegue: `.env` (variables efectivas de la app), `.env.example` (documentación con placeholders), flujo `git-secret` (`app/config.yml.secret`, `setup.sh`).
- Operaciones: rotación de credenciales expuestas (paso manual verificado en tasks; riesgo residual hasta completarla).
