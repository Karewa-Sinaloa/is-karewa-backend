# Tasks

## 1. Modo hash-requerido en el controlador de acceso

- [ ] 1.1 Implementar el estado explícito de "hash requerido" en la declaración de métodos (`app/core/auth/module.access.controller.php`): con hash-requerido, la petición se deniega con el error de acceso estándar (901004) si el hash falta o no valida, aunque `auth` esté deshabilitado — verificado con un escenario nuevo en `tests/ModuleHashAuthTest.php` (patrón subproceso): método hash-requerido sin testigo → 901004 — `./vendor/bin/phpunit --filter ModuleHashAuthTest`
- [ ] 1.2 Verificar con test en ese mismo fichero que un hash válido concede acceso al método hash-requerido sin token de sesión, y que un hash inválido lo deniega — `./vendor/bin/phpunit --filter ModuleHashAuthTest`
- [ ] 1.3 Verificar con test que un JWT válido **no** sustituye al hash en un método hash-requerido (la denegación sigue siendo 901004 sin testigo) — `./vendor/bin/phpunit --filter ModuleHashAuthTest`

## 2. Alineación del payload declarado con el payload emitido

- [ ] 2.1 Cambiar la declaración de `mailings index` (`app/core/modules/mailings/index.php:26`) a hash-requerido con el payload canónico `[code]` derivado del parámetro `code`, y verificar con test de round-trip que una URL construida exactamente como la construye `loginMailer` (`_key=<Create([$code])>&code=...&recovery_hash=...&role=...`) supera la validación — `./vendor/bin/phpunit --filter HashAuthTest` (o test nuevo dedicado al módulo mailings)
- [ ] 2.2 Verificar con test negativo que un hash firmado para otro código (payload distinto) es denegado en `mailings index` — `./vendor/bin/phpunit --filter ModuleHashAuthTest`
- [ ] 2.3 Verificar con test que el acceso directo al endpoint sin `_key` (p. ej. `GET /api/v5/mailings/login-recovery` sin parámetros) responde el error de acceso estándar y no renderiza HTML — `./vendor/bin/phpunit --filter ModuleHashAuthTest`

## 3. Marcador muerto y cobertura de declaraciones

- [ ] 3.1 Eliminar `['hash' => true]` de `users show` (`app/core/modules/users/index.php:9`) y verificar con test que ninguna declaración de módulo usa un payload que el servidor no pueda emitir (inventario de declaraciones: solo payloads reales) — `./vendor/bin/phpunit --filter ModuleHashAuthTest` (o test nuevo de declaraciones)
- [ ] 3.2 Verificar con grep de inventario que solo los módulos con flujo de enlaces declaran hash (`grep -rn "'hash' =>" app/ api/` devolviendo únicamente `mailings`) — comando ejecutado y su salida revisada en la PR

## 4. Verificación de integración

- [ ] 4.1 Ejecutar la suite completa `./vendor/bin/phpunit` y verificar que pasa sin regresiones
- [ ] 4.2 Verificar con `openspec validate enforce-hash-auth-requirements` que el cambio es válido
