# Monitor Karewa 2.0
Monitor Karewa es la segunda versión actualizada del sitema creado originalmente por [Karewa.org](https://karewa.org). Se han cambiado los lenguajes utilizados originalmente (MongoDB, Express.js, Vue.js, Node.js) por tecnologias mas compatibles con las necesidades del proyecto, como son (MySQL, PHP y Vue.js) separando en frontend y backend con un API RESTful.
En el sistema inicial habian algunos problemas para escalar el proyecto, también habian problemas con la base de datos NoSQL (MongoDB) que dificultaban la integridad de los datos y la realización de consultas complejas. Por ello se ha optado por una base de datos relacional (MySQL) que facilita estas tareas y es más compatible con las necesidades del proyecto. Otro de los problemas encontrados con la version inicial es que, aunque el sistema presentaba poco uso, el rendimiento no era el optimo y se encontraban problemas con Express.js y Node,js que generaban muchos logs los cuales presentaban problemas de rendimiento del servidor. Por ello se ha optado por utilizar PHP en el backend, que es un lenguaje más maduro y con mejor rendimiento en este tipo de aplicaciones.

## Tecnologías utilizadas
- Frontend: Vue.js
- Backend: PHP (Framework desarrolloado por [Chavo Digital](https://chavodigital.com))
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
   git clone git@github.com:CrisElGeek/is-karewa-backend.git
   ```
2. Navega al directorio del proyecto:
   ```bash
   cd is-karewa-backend
   ```
3. Instala las dependencias de PHP utilizando Composer, estas se encuentran en el directorio `app/core/third_party`:
   ```bash
   composer install firebase/php-jwt
   composer install phpmailer/phpmailer
   composer install symfony/yaml
   composer install curl/curl
   composer install ramsey/uuid
   composer install smarty/smarty
   ```
   Adicionalmente se puede instalar la dependencia `phpunit` para pruebas unitarias:
   ```bash
   composer install --dev phpunit/phpunit
   ```
4. Configura la base de datos MySQL y crea una base de datos para Monitor Karewa.
5. Importa el archivo `database/karewa_monitor.sql` en tu base de datos MySQL para crear las tablas necesarias.
6. Configura el archivo de configuración `app/config.yml` con los detalles de tu base de datos y otras configuraciones necesarias. Se deja un archivo de ejemplo `app/config.example.yml` que puedes copiar y renombrar a `config.yml` para facilitar la configuración.
7. Configura tu servidor web (Apache/Nginx) para que apunte al directorio `public` del proyecto.

## Estatus del proyecto
El proyecto se encuentra en desarrollo activo. Se están implementando nuevas funcionalidades y mejoras continuamente. El proyecto aun se encuentra en fase temprana, por lo que se recomienda utilizarlo con precaución en entornos de producción.

## Contribuciones
Las contribuciones son bienvenidas. Si deseas contribuir al proyecto, por favor abre un issue o envía un pull request con tus cambios.

## Licencia
Este proyecto está licenciado bajo la Licencia MIT. Consulta el archivo LICENSE para más detalles.

## Contacto
Para cualquier consulta o soporte, por favor contacta a [Chavo Digital](https://chavodigital.com).



