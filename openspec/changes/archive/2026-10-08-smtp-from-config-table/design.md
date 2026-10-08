# Design

## Context

Ver `proposal.md` (Why) para la motivación. Solo el contexto necesario para la decisión:

- `app/core/config/base.php` carga `app/config.yml` en `$_config` y, en la línea 40, aplica ya el overlay de variables `MAIL_*` sobre `$_config->mailing`. Ese punto de ejecución ocurre **antes** de que exista la capa de datos: `app/core/bootstrap/init.php` incluye `model/get.php` y solo después `helpers/api_configuration.php`.
- `helpers/api_configuration.php` consulta la tabla `config` en cada petición y deja una propiedad por `slug` en `$_apiConfig`, así que `$_apiConfig->smtp_config` es el string JSON de la fila (hoy `{"from":…,"host":…,"port":465,"user":…,"pass":…,"security":"ssl"}`).
- Hay exactamente dos lectores de la sección de correo: `ApiMailer::Send()` (transporte) y `app/core/modules/access/local_login.php:309` (remitente del correo de recuperación).
- `tests/CorsHeadersTest.php` ejecuta `base.php` por separado, sin base de datos, así que `base.php` no puede resolver la fila.

## Goals / Non-Goals

**Goals**

- Un solo punto que resuelva las settings de correo con la prelación entorno > fila > archivo, usado por ambos lectores.
- Sin cambio de esquema, sin consulta adicional a la base de datos y sin reinicio para reflejar un cambio en la fila.
- La capa de `app/config.yml` sigue existiendo como respaldo, de modo que un despliegue sin la fila sigue mandando correo.

**Non-Goals**

- Cambiar la API del módulo `config` ni el shape de su respuesta.
- Cifrar o ocultar credenciales en la tabla `config` (ver Open Questions).
- Reescribir el armado del mensaje en `ApiMailer::Send()` o la plantilla de recuperación.
- Cambiar el stack local de Mailpit ni la documentación de desarrollo.

## Decisions

### 1. Resolución perezosa en el momento del envío

`resolve_mailing_settings()` se llama dentro de `ApiMailer::Send()` y en `local_login.php` al armar el remitente, en lugar de resolver una sola vez al arrancar.

- **Por qué:** la spec exige que una fila actualizada por `PUT /api/v5/config/{id}` surta efecto en el siguiente mensaje. Resolver por envío lo cumple literalmente y sin memoria caché; el costo es un `json_decode` de un string y un par de `getenv`, nada más.
- **Alternativa descartada:** snapshot en `init.php` después de `api_configuration.php`. Requeriría editar cero lectores, pero deja `$_config->mailing` con valores de archivo y obliga a relajar la spec; además `base.php` ya no podría aplicar el overlay de entorno en su línea 40 sin romper la prelación.

### 2. `base.php` deja de aplicar el overlay de entorno

`$_config->mailing` pasa a ser **solo la capa de archivo**; `resolve_mailing_settings()` aplica primero la fila y después `apply_mail_env_overrides()`, de modo que la prelación entorno > fila > archivo se consigue en un solo lugar y sin aplicar el overlay dos veces.

- **Por qué:** aplicar entorno en `base.php` y otra vez después de la fila obligaría a una doble aplicación correcta solo por coincidencia, y dejaría un periodo en el que `$_config->mailing` miente sobre la prelación real.
- **Alternativa descartada:** dejar el overlay en `base.php` y mezclar la fila encima. Haría que la fila pisara a `MAIL_*` hasta la segunda aplicación, exactamente la prelación inversa a la decidida.

### 3. La fila se mapea, no se normaliza

`apply_smtp_config_row($mailing, ?string $value)` decodifica el `value` y escribe: `host`, `port` (entero), `security`, `user`, `password` ← `pass`, `from_email` ← `from`. Además fija `smtp_auth` derivado de `user` y `pass` no vacíos, `debug` en `0` y `from_name` en cadena vacía. Si la fila no existe o el JSON no es objeto, devuelve el objeto intacto y el llamador registra el problema con `error_logs()`.

- **Por qué:** la fila ya existe con ese shape en todos los entornos; mapearla no exige migración ni coordinar con otros consumidores del slug.
- **Alternativa descartada:** exigir en la spec que la fila use las claves del helper (`password`, `from_email`, …). Requeriría actualizar datos en cada despliegue y rompería a quien ya lea la fila directamente.

### 4. Se reutiliza `$_apiConfig` en vez de una consulta propia

La fila ya se carga en cada petición. Una consulta extra en el envío añadiría ida a la base de datos sin ganancia; leer `$_apiConfig->smtp_config` también hace que un cambio publicado por la API del módulo `config` sea visible de inmediato.

### 5. El registro del respaldo entra por una costura inyectable

`resolve_mailing_settings()` lee la capa de archivo de `$_config->mailing` y la fila de `$_apiConfig->smtp_config`, y acepta callables opcionales `$env` y `$log` (por defecto `getenv()` y un cierre que llama a `error_logs()`); cuando falta la fila o el JSON no es objeto invoca `$log` y devuelve la capa de archivo. Los tests fijan los dos globales tal como ya hace `tests/MailerTest.php`, y la inyección de `$log` evita depender del stub global de `error_logs()` ni de la constante `DEBUG_LOG_FILE`, que solo define `base.php`.

## Risks / Trade-offs

- [La fila existe pero tiene valores viejos (el dump apunta a `smtp.zoho.com`)] → el envío falla con la `AppException` 903000 ya documentada; mitigación: verificar/actualizar la fila antes de desplegar y usar `MAIL_*` en local.
- [La fila presente vacía `from_name`] → los correos salen sin nombre de remitente salvo que `MAIL_FROM_NAME` esté definida; queda anotado en el proposal como consecuencia de la decisión 3.
- [Las credenciales SMTP quedan legibles y editables vía `GET/PUT /api/v5/config/{id}` para los roles 1, 2 y 3] → es una exposición **preexistente** (la fila ya está en la tabla y la API ya la sirve), no la introduce este cambio; ver Open Questions.
- [`smtp_auth` derivado puede diferir del valor del archivo] → es el comportamiento especificado; el overlay de entorno sigue pudiendo forzar `MAIL_AUTH=false` para Mailpit.
- [Si la consulta de `config` falla, `$_apiConfig` no se llena] → comportamiento preexistente de `ApiConfiguration`; el respaldo cubre fila ausente, no base de datos caída.

## Migration Plan

1. Confirmar en la base de destino que existe la fila `smtp_config` con host, puerto, seguridad, usuario y contraseña correctos (el dump trae valores de otro proveedor).
2. Desplegar el código: la capa de archivo de `app/config.yml` sigue siendo el respaldo, así que no hay ventana en la que el correo deje de funcionar por la migración.
3. Disparar un correo de recuperación y verificar la entrega; si la fila falta o no es JSON, verificar la entrada en `logs/`.
4. **Rollback:** revertir el commit. No hay datos que revertir; la sección `mailing` de `app/config.yml` nunca dejó de existir.

## Open Questions

- ¿Debe `GET /api/v5/config/{id}` enmascarar el `value` de `smtp_config` para los roles no administradores? Es una exposición preexistente que pertenece a `config-module-fields`, no a este cambio, y no altera la prelación ni las tareas de aquí.
- ¿Conviene añadir una clave `from_name` a la fila en el futuro para dejar de depender de `MAIL_FROM_NAME`? Se resolvería en un cambio aparte porque modificaría el requisito de `mail-delivery` que hoy fija `from_name` vacío.
