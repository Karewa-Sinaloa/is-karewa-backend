---
proyecto: Monitor Karewa Backend (karewa)
fecha: 2026-10-14
alcance: app/ (core, api, modules, templates), httpdocs/, docker/, docker-compose.yml, docker-compose.override.yml, composer.json, composer.lock, app/config.yml, .env, .env.example, SECURITY.md, Makefile, setup.sh
herramientas: revisión manual + composer audit (advisories de dependencias)
---

# Auditoría de seguridad OWASP Top 10:2025 — Monitor Karewa Backend

## Resumen ejecutivo

Se audita la API REST en PHP 8.x (patrón module + ORM propio sobre PDO/MySQL) que conforma el backend de Monitor Karewa. La superficie incluye autenticación JWT (RS256), gestión de sesiones, CRUD genérico por módulos, subida de archivos, envío de correos y despliegue Docker (nginx + php-fpm + MariaDB + cloudflared).

La arquitectura presenta **controles base sólidos**: consultas preparadas con `PDO::ATTR_EMULATE_PREPARES=false`, CORS con lista blanca y rechazo real, rate limiting con validación de proxy de confianza, cabeceras de seguridad centralizadas, contraseñas con `password_hash`, sesión única activa con lista negra de `jti`, y nginx que bloquea la ejecución de PHP salvo `api.php`.

Se confirmaron **18 hallazgos**: **1 Crítico, 3 Altos, 11 Medios y 3 Bajos**. Los riesgos dominantes son: (1) **escalada de privilegios** por mass-assignment de `role_id` en el módulo `users` (cualquier usuario autenticado puede autoasignarse rol 1); (2) **secretos en texto plano** en la configuración activa (credenciales de BD, API key de SendGrid, secreto hCaptcha, secreto HMAC); (3) **subida de archivos sin autenticación** con validación basada únicamente en el `Content-Type` del cliente; y (4) **tokens JWT de un año con el rol embebido**, de modo que una democión de rol no surte efecto hasta la expiración del token.

## Tabla de hallazgos

| ID | Categoría OWASP | Archivo:línea | Nivel de riesgo | Estado de verificación |
| --- | --- | --- | --- | --- |
| F-001 | A01:2025 Broken Access Control | app/core/modules/users/controller.php:137 | 1 | Confirmada |
| F-002 | A02:2025 Security Misconfiguration | app/config.yml:26 | 2 | Confirmada |
| F-003 | A01:2025 Broken Access Control | app/core/modules/attachments/index.php:10 | 3 | Confirmada |
| F-004 | A07:2025 Authentication Failures | app/core/modules/access/local_login.php:91 | 3 | Confirmada |
| F-005 | A08:2025 Software or Data Integrity Failures | app/core/validation/files.php:40 | 4 | Confirmada |
| F-006 | A07:2025 Authentication Failures | app/core/modules/access/local_login.php:170 | 4 | Confirmada |
| F-007 | A02:2025 Security Misconfiguration | app/config.yml:8 | 4 | Confirmada |
| F-008 | A05:2025 Injection | app/core/modules/mailings/login_recovery.php:31 | 4 | Confirmada |
| F-009 | A01:2025 Broken Access Control | app/core/modules/mailings/index.php:26 | 5 | Confirmada |
| F-010 | A03:2025 Software Supply Chain Failures | composer.lock (smarty/smarty v4.5.6) | 5 | Confirmada |
| F-011 | A02:2025 Security Misconfiguration | app/.keys/jwtRS256.key (permisos 0644) | 5 | Confirmada |
| F-012 | A02:2025 Security Misconfiguration | docker/php-fpm/Dockerfile:20 | 5 | Confirmada |
| F-013 | A07:2025 Authentication Failures | app/core/modules/access/local_login.php:160 | 6 | Confirmada |
| F-014 | A06:2025 Insecure Design | app/core/modules/users/controller.php:28 | 6 | Confirmada |
| F-015 | A09:2025 Security Logging and Alerting Failures | app/core/helpers/log.manager.php:15 | 6 | Confirmada |
| F-016 | A02:2025 Security Misconfiguration | httpdocs/probe4.php:1 | 7 | Confirmada |
| F-017 | A02:2025 Security Misconfiguration | docker-compose.override.yml:13 | 7 | Posible riesgo |
| F-018 | A07:2025 Authentication Failures | app/core/modules/users/controller.php:62 | 7 | Confirmada |

## Detalle de hallazgos

### F-001 — Escalada de privilegios por autoasignación de `role_id` en users

- **Categoría OWASP**: A01:2025 — Broken Access Control
- **Archivo:línea**: app/core/modules/users/controller.php:137-142 (update); apoyo en :24 (campo), :56 (reglas) y app/core/bootstrap/midelware.php:458 (queryFields)
- **Nivel de riesgo**: 1 (Crítico)
- **Estado de verificación**: Confirmada
- **Explicación técnica**: El módulo `users` permite `update` a los roles 1–5 (`app/core/modules/users/index.php:11`). El campo `role_id` se declara con `saved` por defecto (`true`) y sin restricción `'roles'`, y las reglas de validación aceptan cualquier `role_id` existente (`numeric|min_value:1|...|exist:roles:id`), incluido el 1. El método `update()` no aplica ninguna guarda equivalente a la que sí existe en `store()` (líneas 92–98, que fuerzan el rol y bloquean la escalada al crear). Para un usuario con rol > 3 el filtro de fila es únicamente `id = USER_ID` (línea 71), de modo que un usuario común puede enviarse un `PUT` con `{"role_id": 1}` y quedar promovido a administrador.
- **Evidencia**: `Users::update()` solo hashea la contraseña y delega en el trait CRUD; `queryFields()` incluye `role_id` porque `availableFields['role_id']['roles']` es `false` (sin filtro de rol).
- **Recomendación**: Aplicar en `update()` las mismas guarda de anti-escalada que en `store()`; además declarar `'roles' => [1, 2, 3]` (o similar) en el campo `role_id` de `$moduleFields` y excluirlo del mass-assignment para roles no autorizados. Validar también `status_id` y demás campos sensibles con la misma disciplina.

### F-002 — Secretos en texto plano en la configuración activa

- **Categoría OWASP**: A02:2025 — Security Misconfiguration (relacionado con A04)
- **Archivo:línea**: app/config.yml:26 (contraseña de BD), :99 (API key de SendGrid), :120 (secreto HMAC de hash-auth), :124 (secreto hCaptcha); también .env:15-16 (credenciales de BD, incluida root)
- **Nivel de riesgo**: 2 (Alto)
- **Estado de verificación**: Confirmada
- **Explicación técnica**: El archivo `app/config.yml` (gitignored, pero presente en el árbol de trabajo con valores reales) contiene credenciales de base de datos, la API key de SendGrid, el secreto de hCaptcha y el secreto HMAC usado por `HashAuth`. El mecanismo `git-secret` existe (`*.secret` cifrados con GPG), pero la configuración efectivamente en uso está en claro junto al código. La exposición de cualquiera de estos secretos permite enviar correo como el dominio, saltarse hCaptcha o firmar tokens hash-auth alternativos.
- **Evidencia**: `config.yml` incluye la contraseña de BD en claro, la API key de SendGrid (`SG.<prefijo>...`), el `hash` HMAC y el secreto de hCaptcha. (Valores reales redactados: rotar estas credenciales, ver recomendación.)
- **Recomendación**: Mover todos los secretos a variables de entorno (patrón ya usado por `mail_env.php`) o al flujo `git-secret`, rotar todas las credenciales expuestas (BD, SendGrid, hCaptcha, HMAC) y evitar que valores reales vivan en el árbol de trabajo fuera del revelado controlado.

### F-003 — Subida de archivos sin autenticación (attachments)

- **Categoría OWASP**: A01:2025 — Broken Access Control
- **Archivo:línea**: app/core/modules/attachments/index.php:10
- **Nivel de riesgo**: 3 (Alto)
- **Estado de verificación**: Confirmada
- **Explicación técnica**: El método `store` del módulo `attachments` se declara con autenticación deshabilitada (`[false, NULL]`), de modo que cualquier cliente anónimo puede subir archivos (imágenes/PDF) al servidor. Esto permite almacenamiento ilimitado desde el exterior (abuso de disco/anchos de banda), alojamiento de contenido malicioso (p. ej. PDFs de phishing) en el dominio de confianza del API, y alimenta el riesgo del hallazgo F-005.
- **Evidencia**: `'store' => [false, NULL]` frente a `'store' => [true, [1, 2, 3]]` en módulos vecinos como `image-upload`.
- **Recomendación**: Exigir autenticación y rol mínimo para subir adjuntos (p. ej. `[true, [1, 2, 3]]`), aplicar cuota por cliente y límite de tamaño/tipo estricto.

### F-004 — Tokens JWT de un año y rol congelado en el token

- **Categoría OWASP**: A07:2025 — Authentication Failures (relacionado con A01)
- **Archivo:línea**: app/core/modules/access/local_login.php:91; app/core/auth/jwt_token.php:40-42; app/core/auth/session.set.php:96-98
- **Nivel de riesgo**: 3 (Alto)
- **Estado de verificación**: Confirmada
- **Explicación técnica**: El login invoca siempre `SessionSet::Login($login_data, true)`, por lo que **todo** token de login dura 1 año (31536000 s), ignorando `SESSION_TIME` (86400 s). El rol se lee del claim `role_id` del JWT y no se revalida contra la base de datos en cada petición: una democión o baja de un usuario no revoca el token vigente (solo la sustituye un nuevo login), de modo que un ex-administrador conserva permisos de admin hasta expirar el token o cerrar sesión. La sesión única activa mitiga parcialmente, pero no cubre demociones.
- **Evidencia**: `SessionSet::Login($login_data, true)` fijo en el login; `define('USER_ROLE', $token_data->data->role_id)` sin consulta a BD.
- **Recomendación**: Respetar `SESSION_TIME` y aplicar `keep_session` solo si el usuario lo solicita; reducir la vida del access token (p. ej. horas) y usar refresh tokens; al validar, refrescar el rol/status desde la BD (o revocar la sesión activa al cambiar `role_id`/`status_id` de un usuario).

### F-005 — Validación de subida por `Content-Type` del cliente

- **Categoría OWASP**: A08:2025 — Software or Data Integrity Failures
- **Archivo:línea**: app/core/validation/files.php:40; app/core/modules/attachments/controller.php:24-32,50
- **Nivel de riesgo**: 4 (Medio)
- **Estado de verificación**: Confirmada
- **Explicación técnica**: `Files::Validate()` decide el tipo permitido usando únicamente `$file["type"]`, cabecera controlada por el atacante, sin inspeccionar la firma (magic bytes) ni la extensión real. En `attachments` el archivo se guarda tal cual (`move_uploaded_file`) con un nombre que preserva letras y puntos (`preg_replace('/([^a-zA-Z0-9\.\r]+)/', '-', ...)`, p. ej. `shell.php` o `nota.pdf.html`). nginx limita la ejecución de PHP a `api.php`, lo que reduce el riesgo de RCE, pero el alojamiento de archivos con tipo engañado sigue permitiendo contenido activo (HTML/JS embebido en PDFs o archivos servidos como estáticos).
- **Evidencia**: `preg_match("#" .$params["formats"] ."#", $file["type"])` como única comprobación; el nombre conserva `.php`.
- **Recomendación**: Validar extensión en lista blanca **y** contenido real (`finfo_file`/MIME sniffing); renombrar los archivos con un nombre aleatorio (p. ej. UUID) y guardar el original solo como metadato; servir los uploads desde un dominio/origen separado sin posibilidad de contenido activo.

### F-006 — Código de recuperación débil y sin límite de intentos

- **Categoría OWASP**: A07:2025 — Authentication Failures (relacionado con A04)
- **Archivo:línea**: app/core/modules/access/local_login.php:170 (generación); :269-273 (verificación)
- **Nivel de riesgo**: 4 (Medio)
- **Estado de verificación**: Confirmada
- **Explicación técnica**: El código de recuperación es un entero de 6 dígitos (`mt_rand(100000, 999999)`): espacio de solo 10⁶ y PRNG no criptográfico (`mt_rand`), predecible si el atacante puede observar muestras. El `Reset` verifica el código con `password_verify` pero no lleva contador de intentos por cuenta ni invalida el código tras fallos; dentro de la ventana de 1 h (`REC_CODE_TIME: 3600`) solo el rate limit global del endpoint (10/min, `sensitive_limit`) acota el fuerza bruta (~600 intentos por ventana). El diseño depende exclusivamente de la secrecy del canal de correo.
- **Evidencia**: `$rec_code = mt_rand(100000, 999999);` y verificación sin registro de intentos fallidos.
- **Recomendación**: Generar el código con `random_int` (o better: token de 128+ bits), limitar intentos fallidos por cuenta/código (p. ej. 5) y invalidar el código tras superarlos o al usarlo; preferiblemente sustituir el código numérico por un enlace de un solo uso con token largo.

### F-007 — Modo desarrollo activo con `display_errors` en la configuración en uso

- **Categoría OWASP**: A02:2025 — Security Misconfiguration
- **Archivo:línea**: app/config.yml:8 (`development: true`); app/core/config/base.php:53-56
- **Nivel de riesgo**: 4 (Medio)
- **Estado de verificación**: Confirmada
- **Explicación técnica**: `base.php` vincula `ini_set('display_errors', ...)` y `display_startup_errors` al flag `development` de la configuración. El `config.yml` presente en el árbol de trabajo (el que se despliega en este entorno) lo tiene en `true`, por lo que cualquier warning/fatal puede volcarse al cliente con rutas internas, fragmentos de SQL y estado interno (el log de errores ya muestra trazas completas con rutas `/var/www/html/...`).
- **Evidencia**: `ini_set('display_errors', $_config->development);` con `development: true`.
- **Recomendación**: Fijar `development: false` en todos los entornos accesibles desde red; usar un flag de entorno (`APP_ENV`) con valor por defecto seguro y no almacenar el modo desarrollo en el mismo archivo de configuración productiva.

### F-008 — XSS reflejado en la plantilla de recuperación de acceso

- **Categoría OWASP**: A05:2025 — Injection (XSS)
- **Archivo:línea**: app/core/modules/mailings/login_recovery.php:17,31; app/templates/views/login_recovery.tpl:12
- **Nivel de riesgo**: 4 (Medio)
- **Estado de verificación**: Confirmada
- **Explicación técnica**: El endpoint público `mailings/login-recovery` (véase F-009) responde `Content-Type: text/html` y construye `login_recovery_link` concatenando el parámetro de usuario `$_GET['recovery_hash']` (y el código en la URL) dentro de un atributo `href` de la plantilla Smarty, sin escapar (`Smarty::$escape_html = false` por defecto). Un valor como `recovery_hash=" onclick=alert(1) x="` produce JavaScript ejecutable en el origen del API, habilitando robo de tokens si viajan en la URL, phishing o manipulación de la página de reset.
- **Evidencia**: `$url . "?h=" . $key` con `$key = trim(strip_tags($_GET['recovery_hash']))` (strip_tags no neutraliza atributos HTML); plantilla `<a href="{$login_recovery_link}">`.
- **Recomendación**: Activar `$smarty->escape_html = true` (o escapar `|escape:'html'` en la plantilla), validar `recovery_hash` contra formato esperado y separar el origen de servido de plantillas del origen de la API.

### F-009 — Hash-auth declarado pero no aplicado (mailings) y marcador inválido (users show)

- **Categoría OWASP**: A01:2025 — Broken Access Control (relacionado con A06)
- **Archivo:línea**: app/core/modules/mailings/index.php:26; app/core/modules/users/index.php:9; lógica en app/core/auth/module.access.controller.php:49-73
- **Nivel de riesgo**: 5 (Medio)
- **Estado de verificación**: Confirmada
- **Explicación técnica**: `ModuleHandler::Authenticate()` solo usa el hash alternativo para **eximir** de un requisito de autenticación; nunca lo convierte en un requisito. En `mailings` el método se declara como `'index' => [false, NULL, ['hash' => $_GET["_key"]]]` — con `auth=false` el hash se calcula y se ignora: el endpoint es totalmente anónimo (de ahí que el XSS de F-008 sea explotable sin credenciales). En `users`, `'show' => [true, [1..5], ['hash' => true]]` declara un payload `['hash' => true]` que jamás coincide con los tokens emitidos por el servidor (muere como alt-auth inofensivo, pero deja una falsa sensación de control). Adicionalmente, la comparación de claims de `HashAuth` exige igualdad exacta del payload, por lo que los enlaces generados con `HashAuth::Create([$code])` no pueden validar contra el payload declarado por el módulo.
- **Evidencia**: `if(!$auth || ($auth && $altAuth)) { ... return true; }` — el método público entra sin verificar el hash.
- **Recomendación**: Introducir un modo "hash requerido" (p. ej. `[true, NULL, $hash]` con exención de JWT, o un tercer estado explícito) que exija `HashAuth::Validate` y rechace si falla; alinear el payload declarado con el payload con que se firman los enlaces; eliminar el marcador `['hash' => true]` sin uso.

### F-010 — Dependencias con vulnerabilidades publicadas (Smarty, Symfony YAML)

- **Categoría OWASP**: A03:2025 — Software Supply Chain Failures
- **Archivo:línea**: composer.lock — smarty/smarty v4.5.6; symfony/yaml v5.4.45
- **Nivel de riesgo**: 5 (Medio)
- **Estado de verificación**: Confirmada
- **Explicación técnica**: `composer audit` reporta 5 avisos en 2 paquetes instalados: Smarty < 4.5.7 — CVE-2026-62993 (SSRF vía `{fetch}` con bypass de `trusted_uri`) y CVE-2026-62992 (traversal por symlink fuera de directorios de confianza), ambos severidad media; Symfony YAML < 5.4.52 — CVE-2026-45304 (Billion Laughs), CVE-2026-45305 (ReDoS) y CVE-2026-45133 (stack exhaustion), severidad baja. La explotabilidad actual es limitada (las plantillas Smarty son locales y fijas; el YAML parseado es configuración propia), pero el proyecto carece de proceso visible de actualización de dependencias.
- **Evidencia**: salida de `composer audit` (5 advisories / 2 packages).
- **Recomendación**: Actualizar `smarty/smarty` a ≥ 4.5.7 y `symfony/yaml` a ≥ 5.4.52; incorporar `composer audit` al pipeline CI y fijar política de parcheo.

### F-011 — Permisos inseguros de clave JWT, logs y directorios de subida

- **Categoría OWASP**: A02:2025 — Security Misconfiguration
- **Archivo:línea**: app/.keys/jwtRS256.key (0644); app/core/helpers/log.manager.php:15-19; logs/ (0777); app/core/modules/image-upload/controller.php:17 y attachments/controller.php:17 (`permissions => 0777`)
- **Nivel de riesgo**: 5 (Medio)
- **Estado de verificación**: Confirmada
- **Explicación técnica**: La clave privada RS256 usada para firmar JWT está legible por todos los usuarios del sistema (`-rw-r--r--`), pese a que `setup.sh` ordena `chmod 600`; en un host compartido o si otro servicio se ve comprometido, la clave permite firmar tokens administrativos válidos. El directorio `logs/` y los archivos creados por `error_logs()` usan permisos 0777 (además de un `chmod` aplicado al recurso de archivo en lugar de la ruta), y las carpetas de uploads se crean con 0777.
- **Evidencia**: `ls -la app/.keys` muestra `-rw-r--r--` para `jwtRS256.key`; `mkdir(..., 0777)` / `chmod($lfile, 0777)` en `log.manager.php`.
- **Recomendación**: Restaurar 0600 en la clave privada y 0700 en `.keys/`; usar 0750/0640 para logs y 0755 para uploads; corregir el `chmod` para que aplique a la ruta; añadir verificación de permisos al arranque.

### F-012 — Xdebug habilitado de forma permanente en la imagen PHP

- **Categoría OWASP**: A02:2025 — Security Misconfiguration
- **Archivo:línea**: docker/php-fpm/Dockerfile:20-21
- **Nivel de riesgo**: 5 (Medio)
- **Estado de verificación**: Confirmada
- **Explicación técnica**: La imagen `php:8.4-fpm` instala y habilita Xdebug sin condición de entorno. Con un debug client accesible (o `xdebug.mode=debug`/`start_with_request` por defecto según la versión) un atacante podría iniciar sesiones de depuración contra el contenedor, obtener ejecución asistida o degradar el rendimiento; además ensucia trazas en errores.
- **Evidencia**: `pecl install xdebug && docker-php-ext-enable xdebug` incondicional en el Dockerfile.
- **Recomendación**: No instalar Xdebug en la imagen base; si se necesita para desarrollo, usar una build/etapa separada con el flag deshabilitado por defecto (`XDEBUG_MODE=off`) y sin exposición de red del puerto de depuración.

### F-013 — Enumeración de cuentas en el flujo de recuperación

- **Categoría OWASP**: A07:2025 — Authentication Failures (relacionado con A01)
- **Archivo:línea**: app/core/modules/access/local_login.php:160-162
- **Nivel de riesgo**: 6 (Medio)
- **Estado de verificación**: Confirmada
- **Explicación técnica**: `Recovery()` responde `404000` cuando el correo no corresponde a un usuario activo y `SUCCESS` cuando existe (aunque el correo posterior falle). Esto permite a un atacante enumerar direcciones de correo registradas contra el endpoint (con rate limit de 10/min, pero suficiente para diccionarios). El login también tiene diferencias de tiempo (solo ejecuta `password_verify` si el usuario existe).
- **Evidencia**: `if (!$user_data) { ApiResponse::Set(404000); }` antes de generar el código.
- **Recomendación**: Responder siempre de forma idéntica (éxito simulado) independientemente de la existencia de la cuenta; usar comparación de hash contra un dummy en rama vacía para igualar tiempos.

### F-014 — Mass-assignment de `recovery_code`/`recovery_date` en users

- **Categoría OWASP**: A06:2025 — Insecure Design
- **Archivo:línea**: app/core/modules/users/controller.php:28-29
- **Nivel de riesgo**: 6 (Medio)
- **Estado de verificación**: Confirmada
- **Explicación técnica**: Los campos `recovery_code` y `recovery_date` se declaran con `optional => true` pero con `saved` por defecto (`true`), de modo que si aparecen en el payload de un `store`/`update` se persisten. Un usuario podría escribir directamente su `recovery_code` (en claro, no hasheado) y luego usar el endpoint `Reset` con ese valor, o corromper el flujo de recuperación de otras filas (para roles ≤ 3 el filtro de actualización alcanza a otros usuarios con `role_id >= USER_ROLE`). El código legítimo se guarda hasheado; un valor en claro escrito por API rompe esa invariante.
- **Evidencia**: `'recovery_code' => ['field' => 'u.recovery_code', 'filter' => false, 'listed' => false, 'optional' => true]` sin `'saved' => false`.
- **Recomendación**: Marcar ambos campos con `'saved' => false` (gestión exclusiva del flujo `access`), o filtrarlos explícitamente en `queryFields()` para requests de usuario.

### F-015 — Registro de eventos de seguridad débil (logs 0777, secretos en URLs, sin alertado)

- **Categoría OWASP**: A09:2025 — Security Logging and Alerting Failures
- **Archivo:línea**: app/core/helpers/log.manager.php:15-19,29; app/core/modules/access/local_login.php:316-317
- **Nivel de riesgo**: 6 (Medio)
- **Estado de verificación**: Confirmada
- **Explicación técnica**: Se registran eventos útiles (401/403, fallos JWT), pero: (a) los archivos de log se crean con permisos 0777 en un directorio 0777, editables por cualquier proceso local; (b) el código de recuperación viaja en la query string de la URL que el servidor descarga (`file_get_contents(API_URL . 'mailings/login-recovery?...&code=' . $code)`), de modo que puede quedar en access logs del host de mailings y en proxies; (c) no hay mecanismo de alertado ni correlación de eventos de seguridad (fuerza bruta, escaladas, subidas anónimas); (d) si la escritura del log falla, la petición muere con un `die` que revela la ruta del log.
- **Evidencia**: `'Code: ' . $db_code` en logs (hash, aceptable), pero el código en claro viaja por URL; `chmod(..., 0777)`; `die(json_encode([... 'no can write to log file ' . $file ...]))`.
- **Recomendación**: Permisos restringidos (0640/0750) y volumen de logs separado; eliminar el código de la query string (usar solo el hash de un solo uso en la URL del template); emitir alertas sobre picos de 401/403/429; no revelar rutas en errores de logging.

### F-016 — Endpoint de diagnóstico público `probe4.php`

- **Categoría OWASP**: A02:2025 — Security Misconfiguration
- **Archivo:línea**: httpdocs/probe4.php:1
- **Nivel de riesgo**: 7 (Bajo)
- **Estado de verificación**: Confirmada
- **Explicación técnica**: Archivo de prueba en la raíz web que devuelve `REMOTE_ADDR` y `HTTP_CF_CONNECTING_IP`. Confirma al atacante detalles de la topología de red/proxies y puede usarse para verificar si su IP llega al origen. Aunque nginx solo ejecuta `api.php` vía PHP-FPM (este archivo cae en `try_files`/fallback), su presencia en el árbol publicable es un residuo de depuración.
- **Evidencia**: `echo json_encode(['remote'=>..., 'cf'=>...]);`
- **Recomendación**: Eliminar `probe4.php` del repositorio y de los despliegues; mantener las sondas de diagnóstico detrás de autenticación o solo en el healthcheck interno (`/healthz`).

### F-017 — Puerto de base de datos expuesto y credenciales root en `.env` (entorno de desarrollo)

- **Categoría OWASP**: A02:2025 — Security Misconfiguration
- **Archivo:línea**: docker-compose.override.yml:13-14; .env:15-16
- **Nivel de riesgo**: 7 (Bajo)
- **Estado de verificación**: Posible riesgo
- **Explicación técnica**: El override de desarrollo publica MariaDB en el puerto del host (`3307`) y `.env` contiene la contraseña de root de la BD junto a la de la aplicación. En un host de desarrollo privado es aceptable, pero si este override se usa en un servidor accesible desde Internet (o el `.env` se copia a producción) la BD queda expuesta con credenciales root adivinables/compartidas.
- **Evidencia**: `db: ports: - ${DB_HOST_PORT:-3306}:3306` en el override; `DB_ROOT_PASSWORD=*21JuLi07$`.
- **Recomendación**: No publicar puertos de BD en entornos compartidos; usar `127.0.0.1:3307:3306` como mínimo; separar secretos de dev/prod y no reutilizar la contraseña root.

### F-018 — Política de contraseñas débil e inconsistente

- **Categoría OWASP**: A07:2025 — Authentication Failures
- **Archivo:línea**: app/core/modules/users/controller.php:62 (`min:6`), :100 (`min:8` solo en store); app/core/modules/access/local_login.php:41 (`min:6`)
- **Nivel de riesgo**: 7 (Bajo)
- **Estado de verificación**: Confirmada
- **Explicación técnica**: La creación de usuarios exige mínimo 8 caracteres, pero la actualización usa la regla de clase `password => min:6`, permitiendo reducir una contraseña a 6 caracteres sin complejidad (sin mayúsculas/números/símbolos ni verificación contra diccionarios). En combinación con el auto-login posterior al reset (que emite sesión), políticas mínimas debilitan la resistencia a fuerza bruta offline si la BD se filtra (aunque se use `password_hash` con bcrypt por defecto).
- **Evidencia**: `$this->rules['password'] = 'required|min:8'` solo en `store()`; `update()` no lo sobreescribe.
- **Recomendación**: Regla única de política (mínimo 10–12 caracteres, reutilización/contraseñas comunes bloqueadas) aplicada en store, update y reset; considerar `PASSWORD_ARGON2ID` si está disponible.

## Aspectos que requieren pruebas

- [ ] **Contenido real de la tabla `config` (A01:2025)**: verificar que ninguna fila con secretos (p. ej. `smtp_config`, que incluye `pass`) tenga `is_private = 0`, ya que el listado anónimo del módulo `config` expondría su `value`. Mecanismo de visibilidad revisado en código; los datos no son inspeccionables por revisión estática.
- [ ] **Despliegue real de nginx (A02:2025 / A08:2025)**: confirmar en el servidor de producción que la configuración efectiva es la de `docker/nginx/default.conf` (bloqueo de `.php` fuera de `api.php`, sin alias de directorios peligrosos) y que los uploads no se sirven con `Content-Disposition` ejecutable.
- [ ] **Registro abierto de usuarios (A06:2025)**: `users.store` es público con rol forzado 5 y auto-login. Confirmar con negocio si el alta anónima es intencional y si requiere verificación de correo u hCaptcha (hCaptcha no se exige en este endpoint).
- [ ] **Explotabilidad dinámica del XSS reflejado (F-008)**: verificar en el entorno desplegado los encabezados de mitigación del navegador (CSP no está presente) y el impacto real en el navegador del usuario final.
- [ ] **Tablas de sesiones en producción**: confirmar que `user_sessions` y `token_blacklist` existen con los índices únicos descritos; su ausencia provocaría fallos 902000 en toda autenticación (disponibilidad, no confidencialidad).
- [ ] **Rotación histórica de secretos (F-002)**: dado que `app/config.yml` y `.env` con valores reales estuvieron en claro en el workstation, evaluar si las credenciales (SendGrid, BD, hCaptcha) deben considerarse comprometidas y rotarse.
- [ ] **Rate limiting efectivo tras Cloudflare (A04/A07)**: validar en producción que `CF-Connecting-IP` llega únicamente desde los rangos de `trusted_proxies` y que no hay ruta de bypass (p. ej. IPv6 no listado o header dual).

---

**Controles positivos observados** (para contexto): consultas 100% parametrizadas en la capa modelo con emulación desactivada; CORS con lista blanca y rechazo HTTP 403 real, sin combinar `*` con credenciales; rate limiting por endpoint con identidad de cliente protegida contra spoofing; JWT RS256 con sesión única activa, lista negra de `jti` y revocación ante token superseded; hash-auth con HMAC-SHA256, `hash_equals` y expiración; cabeceras de seguridad (nosniff, X-Frame-Options, Referrer-Policy, HSTS) centralizadas; nginx que impide ejecutar PHP en uploads; manejo de secretos vía git-secret para archivos `*.secret`; respuestas de error basadas en códigos (sin volcado de mensajes SQL al cliente).
