# Tasks

## 1. Esquema y módulo config

- [x] 1.1 Crear `resources/migrations/config_edit_roles.sql` con `ALTER TABLE dev_config ADD COLUMN edit_roles VARCHAR(32) NOT NULL DEFAULT '1,2,3';` y agregar la columna a los dumps `app/karewa_dev_dev.sql` y `resources/karewa_dev.sql`. Verificar con `grep -n "edit_roles" resources/migrations/config_edit_roles.sql app/karewa_dev_dev.sql resources/karewa_dev.sql` (los tres archivos la muestran con `DEFAULT '1,2,3'`).
- [x] 1.2 Declarar `edit_roles` en `$moduleFields` de `app/core/modules/config/controller.php` con `listed => true`, `saved => false`, `filter => true`, y añadir `'edit_roles' => ['column' => 'edit_roles', 'always' => [1]]` a `$get_params`. Verificar con `php -l app/core/modules/config/controller.php` y con `./vendor/bin/phpunit tests/ConfigFieldMappingTest.php` tras ampliarlo para afirmar que el campo existe, que no es escribible y que la declaración de permisos apunta a esa columna.

## 2. Núcleo: autorización por fila

- [x] 2.1 Agregar `901009` (`APP_AUTH_ROW_FORBIDDEN`, `403`) a `app/core/config/api_codes.yml` y el chequeo en `BaseModel::init()` (`app/core/bootstrap/midelware.php`): en `update` y `destroy`, cargar la fila por id y aceptar solo si `USER_ROLE` está en `edit_roles` o es el rol 1 (`always`), respondiendo `901009` sin tocar datos cuando no lo esté. Verificar con `tests/ConfigEditRolesTest.php`: rol en la lista actualiza, rol fuera de la lista recibe `901009` con la fila intacta y el rol 1 actualiza aunque no esté en la lista (`./vendor/bin/phpunit tests/ConfigEditRolesTest.php`).
- [x] 2.2 Comprobar en el mismo test que `store` no ejecuta el chequeo y que la fila nace con `1,2,3`, y que un payload que incluya `edit_roles` se responde con éxito sin modificar la lista almacenada. Verificar con `./vendor/bin/phpunit tests/ConfigEditRolesTest.php`.

## 3. Contrato OpenAPI y documentación

- [x] 3.1 En `tools/generate-openapi.php`: usar la selección `crud` más `901009` para las operaciones del módulo `config` y devolver `1,2,3` como ejemplo de `edit_roles`. Verificar con `php -l tools/generate-openapi.php`.
- [x] 3.2 Regenerar el contrato con `php tools/generate-openapi.php` y verificar con `./vendor/bin/phpunit tests/OpenApiSyncTest.php` más una aserción de que `PUT /config/{id}` y `DELETE /config/{id}` documentan `403` con `APP_AUTH_ROW_FORBIDDEN`, que `edit_roles` aparece como parámetro de `GET /config` y en los ejemplos de respuesta, y que no aparece en los bodies de `POST /config` ni de `PUT /config/{id}`.
- [x] 3.3 Revisar la sección «Permisos de edición por fila en `config`» de `README.md` y confirmar que describe lo implementado: formato `1,2,3`, default para filas existentes, piso del rol 1, configuración solo desde la base de datos y el código `901009`. Verificar con `grep -n "edit_roles" README.md`.
- [x] 3.4 Correr la suite completa `./vendor/bin/phpunit` y confirmar 0 fallos (los 2 warnings y 2 deprecations preexistentes de `update.php`/`fields.php` se mantienen).
