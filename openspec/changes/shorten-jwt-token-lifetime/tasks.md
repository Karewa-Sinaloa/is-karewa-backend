# Tasks

## 1. Vida del token ligada a la sesión configurada

- [ ] 1.1 Eliminar el `keep_session=true` fijo del login y derivar `exp` de `SESSION_TIME` salvo opt-in explícito (`keep_session` opcional en el payload de login, booleano, por defecto false), y verificar con test en `tests/JWTKeyEncodeTest.php` que el token emitido sin opt-in expira en `time() + SESSION_TIME` (86400 s) — `./vendor/bin/phpunit --filter JWTKeyEncode`
- [ ] 1.2 Añadir test de opt-in: con `keep_session` presente y true, `exp` = `time() + 31536000`, y sin él vuelve a `SESSION_TIME` — `./vendor/bin/phpunit --filter JWTKeyEncode`
- [ ] 1.3 Verificar con test que el payload de login rechaza un `keep_session` no booleano (validación del campo) — `./vendor/bin/phpunit --filter <test>`

## 2. Revalidación de rol y estado por petición

- [ ] 2.1 Implementar la revalidación de `role_id`/`status_id` contra la BD en el camino de autenticación obligatoria, revocando la sesión y respondiendo 901008 (sesión revocada) cuando difieran del token, y verificar con test en `tests/SessionSetTest.php` que un token activo con rol cambiado en BD es rechazado — `./vendor/bin/phpunit --filter SessionSet`
- [ ] 2.2 Añadir test de baja de usuario (`status_id` inactivo en BD con token aún válido): la petición se rechaza y la sesión activa queda revocada — `./vendor/bin/phpunit --filter SessionSet`
- [ ] 2.3 Añadir test de caso sin cambio: rol/estado idénticos en BD y token → acceso concedido con el rol actual de BD — `./vendor/bin/phpunit --filter SessionSet`
- [ ] 2.4 Cubrir el camino de autenticación oportunista: con rol cambiado en BD el token no se acepta como autenticado y la petición pública corre como anónima, verificado con test en `tests/ModuleOpportunisticAuthTest.php` — `./vendor/bin/phpunit --filter ModuleOpportunisticAuth`
- [ ] 2.5 Verificar con test que un error de consulta durante la revalidación deniega el acceso en lugar de confiar en el claim del token — `./vendor/bin/phpunit --filter SessionSet`

## 3. Documentación del contrato

- [ ] 3.1 Documentar en la respuesta de login el campo `expires_in` derivado de `SESSION_TIME` y el nuevo campo `keep_session`, verificando con `./vendor/bin/phpunit --filter OpenApiSync` que el documento OpenAPI sigue sincronizado

## 4. Verificación de integración

- [ ] 4.1 Ejecutar la suite completa `./vendor/bin/phpunit` y verificar que pasa sin regresiones
- [ ] 4.2 Verificar con `openspec validate shorten-jwt-token-lifetime` que el cambio es válido
