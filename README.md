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
6. Configura el archivo de configuración `app/config.yml` con los detalles de tu base de datos y otras configuraciones necesarias. Se deja un archivo de ejemplo `app/config.example.yml` que puedes copiar y renombrar a `config.yml` para facilitar la configuración.
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

## Estatus del proyecto
El proyecto se encuentra en desarrollo activo. Se están implementando nuevas funcionalidades y mejoras continuamente. El proyecto aun se encuentra en fase temprana, por lo que se recomienda utilizarlo con precaución en entornos de producción.

## Contribuciones
Las contribuciones son bienvenidas. Si deseas contribuir al proyecto, por favor abre un issue o envía un pull request con tus cambios.

## Licencia
Este proyecto está licenciado bajo la Licencia MIT. Consulta el archivo LICENSE para más detalles.

## Contacto
Para cualquier consulta o soporte, por favor contacta a [Karewa Sinaloa](dev@karewa.org.mx).
