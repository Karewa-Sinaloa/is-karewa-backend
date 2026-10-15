# Tasks

## 1. Clave JWT y directorio de claves

- [ ] 1.1 Extraer el bloque de claves de `setup.sh` a un paso idempotente propio (p. ej. `tools/jwt-keys.sh`) que genera la clave solo si no existe y siempre aplica `chmod 600` a `app/.keys/jwtRS256.key` y `chmod 700` a `app/.keys/`, invocado desde `setup.sh`; verificar con `chmod 644 app/.keys/jwtRS256.key && bash tools/jwt-keys.sh && stat -c '%a' app/.keys/jwtRS256.key app/.keys` que devuelve `600` y `700` sin sustituir la clave
- [ ] 1.2 Corregir en el worktree actual la deriva de la clave (`chmod 600 app/.keys/jwtRS256.key; chmod 700 app/.keys/`) y verificar con `stat -c '%a'` los modos 0600/0700 — comando `stat -c '%a' app/.keys/jwtRS256.key app/.keys`
- [ ] 1.3 Añadir `tests/FilesystemPermissionsTest.php` (patrón de bootstrap de `openspec/specs/testing`) que afirme 0600 en la clave y 0700 en el directorio cuando existan, y verificar — `./vendor/bin/phpunit --filter FilesystemPermissions`

## 2. Permisos de logs

- [ ] 2.1 Corregir `app/core/helpers/log.manager.php:15-19`: crear el directorio con 0750, el fichero con 0640 y aplicar el `chmod` sobre la **ruta** (hoy sobre el handle de `fopen`), eliminando todo uso de 0777 — verificado con el test del paso 2.2
- [ ] 2.2 Extender `tests/FilesystemPermissionsTest.php` con un caso que escriba la primera entrada en un directorio temporal y afirme 0750 en el directorio y 0640 en el fichero, además de que la petición no falla — `./vendor/bin/phpunit --filter FilesystemPermissions`
- [ ] 2.3 Restablecer en el worktree los modos de `logs/` (`chmod 750 logs; chmod 640 logs/*`) y verificar con `stat -c '%a' logs logs/debug.log` que devuelven `750` y `640`

## 3. Directorios de subida

- [ ] 3.1 Cambiar `"permissions" => 0777` por `0755` en `app/core/modules/image-upload/controller.php:17` y `app/core/modules/attachments/controller.php:17`, y verificar con un caso del test que una carpeta nueva creada para un upload queda en 0755 — `./vendor/bin/phpunit --filter FilesystemPermissions`
- [ ] 3.2 Quitar el bit de escritura a otros usuarios en las carpetas de subida existentes (`chmod -R o-w statics/uploads`) y verificar con `find statics/uploads -type d -perm -0002` que no devuelve ninguna carpeta world-writable

## 4. Detección de deriva

- [ ] 4.1 Añadir `tools/check-permissions.sh` y el objetivo `make check-perms` (más su línea en `help`), que compruebe clave, `app/.keys/`, `logs/`, ficheros de log y carpetas de subida e informe de ruta, modo encontrado y modo esperado; verificar que sale con 0 sobre el worktree corregido — comando `make check-perms; echo $?`
- [ ] 4.2 Verificar que la puerta falla en voz alta: ensanchar la clave a 0644, ejecutar `make check-perms` y comprobar que lista `app/.keys/jwtRS256.key` con encontrado `644` y esperado `600` y sale con código distinto de 0, restaurando después el modo — comando `make check-perms; echo $?`
- [ ] 4.3 Documentar en `README.md` y `SECURITY.md` los modos requeridos y el comando `make check-perms`, y verificar que los comandos documentados se ejecutan tal cual están escritos

## 5. Verificación de integración

- [ ] 5.1 Ejecutar la suite completa `./vendor/bin/phpunit` y verificar que pasa sin regresiones
- [ ] 5.2 Ejecutar `make check-perms` y verificar que reporta cero desviaciones
- [ ] 5.3 Verificar con `openspec validate restrict-file-permissions` que el cambio es válido
