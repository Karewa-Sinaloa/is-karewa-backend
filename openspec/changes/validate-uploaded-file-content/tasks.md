# Tasks

## 1. Validación por extensión y contenido real

- [ ] 1.1 Implementar en `app/core/validation/files.php` la validación combinada (whitelist de extensión + sniffing de contenido real) manteniendo el error 906002 (HTTP 415), y verificar con test unitario nuevo que un archivo con `Content-Type` falsificado y contenido de otro tipo es rechazado — `./vendor/bin/phpunit --filter <nombre-del-test>`
- [ ] 1.2 Verificar con test que una extensión fuera de la whitelist se rechaza con el mismo error — `./vendor/bin/phpunit --filter <nombre-del-test>`
- [ ] 1.3 Verificar con test de aceptación que archivos legítimos de cada tipo permitido (jpg/png/gif/pdf según módulo) superan la validación con su contenido real — `./vendor/bin/phpunit --filter <nombre-del-test>`

## 2. Almacenamiento con nombre aleatorio

- [ ] 2.1 Cambiar el almacenamiento en `app/core/modules/attachments/controller.php` para usar nombre aleatorio (UUID) con la extensión validada e id de carpeta aleatorio, conservando el nombre original solo como metadato en la respuesta; verificar con test que la ruta almacenada no contiene ninguna parte del nombre original — `./vendor/bin/phpunit --filter <nombre-del-test>`
- [ ] 2.2 Verificar con test que la respuesta incluye el nombre original como metadato junto al nombre aleatorio — `./vendor/bin/phpunit --filter <nombre-del-test>`
- [ ] 2.3 Verificar con test que una validación fallida no persiste archivo ni directorio y no altera el volumen almacenado — `./vendor/bin/phpunit --filter <nombre-del-test>`

## 3. Servido no ejecutable

- [ ] 3.1 Verificar con test (o comprobación documentada contra el stack local) que un upload servido devuelve tipo fijado por el servidor con `X-Content-Type-Options: nosniff` y no puede ejecutarse como script — `make up` + comprobación HTTP registrada en la tarea
- [ ] 3.2 Documentar la política de servido de uploads (tipo por extensión, sin sniffing, sin ejecución) en la sección de configuración de estáticos y verificar que la documentación coincide con el comportamiento observado — revisión del diff

## 4. Verificación de integración

- [ ] 4.1 Ejecutar la suite completa `./vendor/bin/phpunit` y verificar que pasa sin regresiones
- [ ] 4.2 Verificar con `openspec validate validate-uploaded-file-content` que el cambio es válido
