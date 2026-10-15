# Tasks

## 1. Modo desarrollo controlado por entorno

- [ ] 1.1 Cambiar la resolución del modo desarrollo en `app/core/config/base.php` para que dependa de la variable de entorno `APP_ENV` (activo solo con valor `development`; por defecto apagado) y quitar la clave `development: true` de `app/config.yml`; verificar con test unitario nuevo (estilo `tests/MailConfigEnvTest.php`) que sin la variable `display_errors` queda apagado — `./vendor/bin/phpunit --filter <nombre-del-test>`
- [ ] 1.2 Verificar con test que con `APP_ENV=development` el modo queda activo y `display_errors` se habilita — `./vendor/bin/phpunit --filter <nombre-del-test>`
- [ ] 1.3 Documentar la variable en `.env.example` (valor seguro por defecto) y verificar que el valor de ejemplo mantiene el modo apagado — revisión del diff + arranque local

## 2. No exposición de detalles internos con el modo apagado

- [ ] 2.1 Verificar con test (patrón `tests/ModuleFatalErrorsTest.php`) que un error inesperado con el modo apagado responde el envelope estándar sin trazas, rutas internas ni fragmentos SQL, y que el detalle queda en el log de errores — `./vendor/bin/phpunit --filter <nombre-del-test>`
- [ ] 2.2 Verificar con test que un warning o notice emitido con el modo apagado no aparece en el cuerpo ni en las cabeceras de la respuesta — `./vendor/bin/phpunit --filter <nombre-del-test>`

## 3. Verificación de integración

- [ ] 3.1 Ejecutar la suite completa `./vendor/bin/phpunit` y verificar que pasa sin regresiones
- [ ] 3.2 Verificar con `openspec validate disable-dev-display-errors` que el cambio es válido
