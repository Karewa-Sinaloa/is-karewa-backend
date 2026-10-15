# Tasks

## 1. Autenticación y roles en attachments

- [ ] 1.1 Cambiar la declaración de `store` en `app/core/modules/attachments/index.php` a `[true, [1, 2, 3]]` y verificar con test nuevo (patrón `tests/ModuleHandlerAllowedRolesTest.php`) que una petición sin token recibe el error estándar de autenticación (401) — `./vendor/bin/phpunit --filter <nombre-del-test>`
- [ ] 1.2 Verificar con test que un token con rol fuera de 1-3 recibe el error estándar de autorización y que no se crea ningún archivo ni directorio — `./vendor/bin/phpunit --filter <nombre-del-test>`
- [ ] 1.3 Verificar con test que un token de rol 1, 2 o 3 sube un archivo válido con éxito y la respuesta usa el envelope estándar — `./vendor/bin/phpunit --filter <nombre-del-test>`

## 2. Límite de tamaño por archivo

- [ ] 2.1 Aplicar el límite de tamaño por archivo en la validación de la subida (configurable en `app/config.yml`) y verificar con test que un archivo mayor devuelve el código 906000 (HTTP 413) sin persistir nada — `./vendor/bin/phpunit --filter <nombre-del-test>`
- [ ] 2.2 Verificar con test que un archivo dentro del límite supera esta comprobación y continúa al resto del flujo — `./vendor/bin/phpunit --filter <nombre-del-test>`

## 3. Cuota de almacenamiento por cliente

- [ ] 3.1 Registrar la propiedad de cada adjunto subido contra el `USER_ID` validado y verificar con test que el volumen atribuido al usuario crece con cada subida — `./vendor/bin/phpunit --filter <nombre-del-test>`
- [ ] 3.2 Añadir el código de error de cuota excedida en `app/core/config/api_codes.yml` (HTTP 413) y verificar con test que una subida que excede la cuota devuelve ese código, no persiste archivo y no altera el volumen del usuario — `./vendor/bin/phpunit --filter <nombre-del-test>`
- [ ] 3.3 Documentar los nuevos límites (`uploads` en `app/config.yml` y `.env.example` si se parametrizan) y verificar que el chequeo usa el valor de configuración — test o verificación registrada en la tarea

## 4. Verificación de integración

- [ ] 4.1 Ejecutar la suite completa `./vendor/bin/phpunit` y verificar que pasa sin regresiones
- [ ] 4.2 Verificar con `openspec validate require-auth-file-uploads` que el cambio es válido
