# Tasks

## 1. Actualización de dependencias afectadas

- [ ] 1.1 Actualizar `smarty/smarty` a `>= 4.5.7` y `symfony/yaml` a `>= 5.4.52` dentro de los rangos existentes (`php composer.phar update smarty/smarty symfony/yaml --with-dependencies`) y verificar que `php composer.phar audit --no-interaction` imprime `No security vulnerability advisories found` — comando `php composer.phar audit --no-interaction`
- [ ] 1.2 Verificar con la suite completa que la actualización no rompe nada (render de plantillas Smarty y parseo YAML de `app/config.yml` incluidos) — `./vendor/bin/phpunit`

## 2. Puerta de auditoría y documentación

- [ ] 2.1 Añadir al `Makefile` el objetivo `audit` (que ejecute `composer audit` en el contenedor `php`, patrón de `make test`) y su línea en `help`, y verificar que `make audit` sale con estado 0 sobre el lock actualizado — comando `make audit`
- [ ] 2.2 Verificar que la puerta falla en voz alta: ante un lock con un paquete en rango de aviso, `make audit` termina con código de salida distinto de 0 y lista el paquete (verificación temporal revertida después del check) — comando `php composer.phar audit --no-interaction; echo $?`
- [ ] 2.3 Documentar en `README.md` y `SECURITY.md` el comando de auditoría y la política de parcheo por severidad de `design.md`, y verificar que los comandos documentados se ejecutan tal cual están escritos

## 3. Verificación de integración

- [ ] 3.1 Ejecutar la suite completa `./vendor/bin/phpunit` y verificar que pasa sin regresiones
- [ ] 3.2 Ejecutar `make audit` y verificar que reporta cero avisos de seguridad
- [ ] 3.3 Verificar con `openspec validate update-vulnerable-dependencies` que el cambio es válido
