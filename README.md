# Monitor Karewa 2.0
Monitor Karewa es la segunda versión actualizada del sitema creado originalmente por [Karewa.org](https://karewa.org). Se han cambiado los lenguajes utilizados originalmente (MongoDB, Express.js, Vue.js, Node.js) por tecnologias mas compatibles con las necesidades del proyecto, como son (MySQL, PHP y Vue.js) separando en frontend y backend con un API RESTful.
En el sistema inicial habian algunos problemas para escalar el proyecto, también habian problemas con la base de datos NoSQL (MongoDB) que dificultaban la integridad de los datos y la realización de consultas complejas. Por ello se ha optado por una base de datos relacional (MySQL) que facilita estas tareas y es más compatible con las necesidades del proyecto. Otro de los problemas encontrados con la version inicial es que, aunque el sistema presentaba poco uso, el rendimiento no era el optimo y se encontraban problemas con Express.js y Node,js que generaban muchos logs los cuales presentaban problemas de rendimiento del servidor. Por ello se ha optado por utilizar PHP en el backend, que es un lenguaje más maduro y con mejor rendimiento en este tipo de aplicaciones.

## Tecnologías utilizadas
- Frontend: Vue.js
- Backend: PHP (Framework desarrolloado por uno de nuestros colaboradores)
- Base de datos: MySQL
- Servidor web: Apache/Nginx
- Sistema operativo: Linux (RHEL/CentOS/Almalinux)
- Servidor virtual dedicado o VPS

## Requisitos del Sistema
- PHP 8.4 o superior
- MySQL 8.0 o superior
- Servidor web Apache o Nginx
- Sistema operativo Linux (RHEL/CentOS/Almalinux recomendado)
- Acceso SSH al Servidor
- Composer para la gestión de dependencias de PHP
- Node.js y npm para la gestión de dependencias de Vue.js
- Espacio en disco suficiente para la base de datos y archivos del sistema asi como para el respaldo de archivos descargados.

## Instalación
1. Clona el repositorio en tu servidor: 
   ```bash
   git clone git@github.com:Karewa-Sinaloa/is-karewa-backend.git
   ```
2. Navega al directorio del proyecto:
   ```bash
   cd is-karewa-backend
   ```
3. Instala las dependencias de PHP utilizando Composer desde la raiz del proyecto:
   ```bash
   composer install
   ```
4. Configura la base de datos MySQL y crea una base de datos para Monitor Karewa.
5. Importa el archivo `resources/karewa_dev.sql` en tu base de datos MySQL para crear las tablas necesarias.
6. Configura el archivo de configuración `app/config.yml` (ignorado por git) con los detalles de tu base de datos y demás ajustes necesarios. En el stack Docker local, las variables `MAIL_*` de `.env` sobrescriben la sección `mailing`; consulta `DEV_ENV_MANUAL.md`.
7. Configura tu servidor web (Apache/Nginx) para que apunte al directorio `public` del proyecto.

## Uso
El sistema esta disponible para su uso libre de quien lo desee implementar. El fin de este proyecto es ayudar a mejorar la transparencia y la rendición de cuentas en las instituciones públicas.

## Bypass de hCaptcha (solo administradores)

El inicio de sesión normalmente exige un token válido de hCaptcha. Para pruebas o
para iniciar sesión desde clientes de API como Postman existe un secreto de bypass
por usuario que **solo funciona para cuentas con rol administrador** (`role_id = 1`).

1. Obtén tu secreto (se muestra una sola vez):
   ```bash
   curl -X POST https://<api>/api/v5/hcaptcha \
     -H "Authorization: Bearer <tu_access_token>"
   ```
   La respuesta incluye `hcaptcha_bypass`. El servidor guarda únicamente un hash
   del secreto, así que guárdalo en un lugar seguro.
2. Inicia sesión enviando el secreto en el header `X-HCaptcha-Bypass` (puedes
   omitir el campo `token` de hCaptcha):
   ```bash
   curl -X POST https://<api>/api/v5/access \
     -H "Content-Type: application/json" \
     -H "X-HCaptcha-Bypass: <tu_secreto>" \
     -d '{"email":"admin@example.com","password":"<tu_password>"}'
   ```
3. Rota el secreto cuando quieras volviendo a ejecutar el primer comando; el
   secreto anterior deja de funcionar.

> Advertencia: este mecanismo está pensado solo para pruebas y clientes de
> confianza. No lo uses en producción ni compartas el secreto; si se filtra,
> rótalo de inmediato.

## Autenticación alternativa por hash

Además del JWT, un módulo puede declarar autenticación alternativa por hash para
métodos concretos (por ejemplo webhooks o enlaces compartidos). El token va
ligado al payload del recurso y expira, de modo que un token acuñado para un
recurso no sirve para otro.

- **Formato del token:** `<payload>.<firma>`, donde `<payload>` es el JSON del
  recurso (incluida su expiración) codificado en base64url y `<firma>` es el
  HMAC-SHA256 de ese payload con el secreto `hash` de la configuración, también
  en base64url. Ambas partes se construyen con un único helper, por lo que no
  pueden divergir.
- **Expiración:** el token incluye `exp` (marca de tiempo Unix) derivada de la
  ventana configurable `hash_expiration` en `app/config.yml` (por defecto
  `3600` segundos). Un token expirado se rechaza.
- **Presentación del token:** se envía en el header `X-Hash-Auth` o, como
  respaldo para enlaces existentes, en el parámetro `?_key=`.

```bash
# Acuñar un token para el recurso {'id': 42}
php -r 'require "app/core/auth/hash.auth.php"; echo App\Auth\HashAuth::Create(["id" => 42]);'

# Presentarlo en un header
curl https://<api>/api/v5/<modulo> -H "X-Hash-Auth: <token>"

# O con el respaldo de query string
curl "https://<api>/api/v5/<modulo>?_key=<token>"
```

Al validar, el módulo debe aportar el mismo payload con el que se acuñó el token;
si el payload no coincide, la validación falla.

## Envío de correo (mailer)

`App\Helpers\ApiMailer::Send()` ensambla y envía un mensaje usando las
credenciales SMTP de la sección `mailing` de `app/config.yml`
(`host`, `port`, `security`, `user`, `password`, `from_email`, `from_name`). Los
campos opcionales se aplican solo cuando están presentes y no vacíos, de modo
que un mensaje mínimo no falla.

```php
App\Helpers\ApiMailer::Send([
    // Obligatorios
    'from'      => ['email' => 'dev@chavodigital.com', 'name' => 'Chavo Digital'],
    'to'        => [['email' => 'user@example.com', 'name' => 'User']],
    'subject'   => 'Código de recuperación',
    'html_body' => '<p>Hola</p>',
    'text_body' => 'Hola',
    // Opcionales: se usan solo si se envían con contenido
    'reply_to'    => ['email' => 'reply@example.com', 'name' => 'Reply'],
    'cc'          => [['email' => 'cc@example.com']],
    'bcc'         => [['email' => 'bcc@example.com']],
    'attachments' => [['file' => '/ruta/al/archivo.pdf', 'name' => 'archivo.pdf']],
]);
```

- **Remitente:** `from.email` y `from.name` los aporta quien llama; el flujo de
  recuperación de acceso usa `mailing.from_email` / `mailing.from_name`.
- **Sobrescritura local:** en el stack Docker, las variables `MAIL_HOST`,
  `MAIL_PORT`, `MAIL_SECURITY`, `MAIL_USER`, `MAIL_PASSWORD`, `MAIL_AUTH`,
  `MAIL_FROM_EMAIL` y `MAIL_FROM_NAME` de `.env` sobrescriben la sección `mailing`.
  `.env.example` las deja apuntando al servicio Mailpit, por lo que el correo local
  se captura en `http://localhost:8025`.
- **Contenido:** el asunto y el cuerpo se envían como UTF-8.
- **Fallos:** si el transporte rechaza el mensaje, el helper lanza
  `AppException` con código `903000` y el contexto del mensaje.

## Estatus del proyecto
El proyecto se encuentra en desarrollo activo. Se están implementando nuevas funcionalidades y mejoras continuamente. El proyecto aun se encuentra en fase temprana, por lo que se recomienda utilizarlo con precaución en entornos de producción.

## Contribuciones
Las contribuciones son bienvenidas. Si deseas contribuir al proyecto, por favor abre un issue o envía un pull request con tus cambios.

## Licencia
Este proyecto está licenciado bajo la Licencia MIT. Consulta el archivo LICENSE para más detalles.

## Contacto
Para cualquier consulta o soporte, por favor contacta a [Karewa Sinaloa](dev@karewa.org.mx).
