# Tasks

## 1. Guarda anti-escalada en `update`

- [ ] 1.1 Implementar en `Users::update()` la guarda anti-escalada espejando `store()` (corregir `role_id < USER_ROLE` al rol del caller; roles > 3 no modifican su `role_id`) y verificar con un test nuevo en `tests/UsersFieldMappingTest.php` (o test dedicado) que un PUT con `{"role_id": 1}` de un rol 5 no cambia el rol — `./vendor/bin/phpunit --filter <test>`
- [ ] 1.2 Añadir test de caso administrativo: rol 1 asignando `role_id` 2 a una fila editable persiste el 2, y rol 3 creando con `role_id` 1 persiste el 3 — `./vendor/bin/phpunit --filter <test>`

## 2. Restricción declarativa de campos privilegiados

- [ ] 2.1 Declarar en `$moduleFields` la lista de roles autorizados sobre `role_id` y marcar `status_id` como escritura administrativa (roles 1-3), y verificar con test que el payload de un rol 4/5 con `{"status_id": 2}` no altera el valor almacenado — `./vendor/bin/phpunit --filter <test>`
- [ ] 2.2 Verificar con test que la declaración y la guarda producen el mismo resultado para `role_id` (consistencia entre ambos mecanismos) — `./vendor/bin/phpunit --filter <test>`

## 3. Trazabilidad del intento

- [ ] 3.1 Registrar un evento de seguridad (sin detalle sensible) cuando se corrija un `role_id`/`status_id` intentado, usando el helper `error_logs()` existente, y verificar con test que el evento se emite al intentar la escalada — `./vendor/bin/phpunit --filter <test>`

## 4. Verificación de integración

- [ ] 4.1 Ejecutar la suite completa `./vendor/bin/phpunit` y verificar que pasa sin regresiones
- [ ] 4.2 Verificar con `openspec validate fix-users-role-mass-assignment` que el cambio es válido
