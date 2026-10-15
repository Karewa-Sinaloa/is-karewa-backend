# Tasks

## 1. Escape de valores controlados por el usuario en el HTML

- [ ] 1.1 Activar el escape por defecto en el renderizado de plantillas del módulo `mailings` (y/o el filtro de escape explícito en `app/templates/views/login_recovery.tpl` sobre `{$login_recovery_link}`) y verificar con un test nuevo `tests/MailingsTemplateEscapingTest.php` que un valor con comilla y `onclick` renderizado sale entidades escapadas — `./vendor/bin/phpunit --filter MailingsTemplateEscapingTest`
- [ ] 1.2 Verificar con un test de ese mismo fichero que un enlace legítimo sigue renderizando un `href` navegable apuntando a `<SITE_URL|CMS_URL>/access/reset?h=<hash>` — `./vendor/bin/phpunit --filter MailingsTemplateEscapingTest`

## 2. Validación de formato estricto de los parámetros

- [ ] 2.1 Validar `recovery_hash` contra su formato estricto (alfabeto base64, longitud acotada) y rechazar con respuesta de error cuando no lo cumple, verificando con test que `recovery_hash=" onclick=alert(1) x="` produce un error sin HTML y sin atributo `onclick` — `./vendor/bin/phpunit --filter MailingsTemplateEscapingTest`
- [ ] 2.2 Validar `code` (6 dígitos) y mantener la validación de `role` (1-5), verificando con test que valores fuera de formato responden error sin renderizar la página — `./vendor/bin/phpunit --filter MailingsTemplateEscapingTest`
- [ ] 2.3 Verificar con test de round-trip que una URL construida como la genera `loginMailer` (`_key`, `code`, `recovery_hash`, `role`) supera la validación y renderiza la página — `./vendor/bin/phpunit --filter MailingsTemplateEscapingTest`

## 3. Verificación de integración

- [ ] 3.1 Ejecutar la suite completa `./vendor/bin/phpunit` y verificar que pasa sin regresiones
- [ ] 3.2 Verificar con `openspec validate fix-login-recovery-xss` que el cambio es válido
