# Design

## Context

`mailings/login-recovery` es un endpoint público que `loginMailer()` (`app/core/modules/access/local_login.php:309`) descarga vía `file_get_contents` con la URL `...?_key=<hash>&code=<código>&recovery_hash=<base64(json)>&role=<1-5>` y sirve como cuerpo del correo de recuperación. El controlador hace `$key = trim(strip_tags($_GET['recovery_hash']))` y lo concatena en `$url . "?h=" . $key`, que la plantilla vuelca en `<a href="{$login_recovery_link}">` sin escapar; `strip_tags()` no codifica comillas, con lo que un atributo `onclick` es inyectable. El `role` ya se valida (numérico 1-5), el `recovery_hash` no. Ver hallazgo F-008 en `docs/auditoria-seguridad/auditoria-owasp-2026-10-14.md`; motivación en `proposal.md` — Why.

## Goals / Non-Goals

- **Goals**: hacer imposible el XSS reflejado en las respuestas HTML del módulo `mailings` (escape + validación de formato estricto) sin romper los enlaces de recuperación legítimos.
- **Non-Goals**: separar el origen de servido de plantillas del origen del API (recomendación de la auditoría que requiere cambios de despliegue), añadir cabeceras CSP, y convertir el endpoint en hash-requerido (cubierto por F-009 en `enforce-hash-auth-requirements`).

## Decisions

1. **Escape por defecto en el render + validación estricta de entrada, ambas capas** (defensa en profundidad).
   - *Alternativa A*: solo escape en la plantilla — cualquier parámetro no previsto hoy sigue entrando crudo en futuras plantillas.
   - *Alternativa B*: solo validación de formato — un fallo de validación o un valor futuro no contemplado reabre la vía; además el `href` necesita codificación de todos modos para ser una URL válida.
   - Elección: ambas; el escape es el control que garantiza el requisito "nunca marcado ejecutable", la validación elimina tempranamente valores que ningún enlace legítimo contiene.
2. **Rechazo con error ante `recovery_hash` malformado, en vez de renderizarlo escapado**.
   - *Alternativa*: renderizar cualquier valor escapado — más permisivo, pero un `recovery_hash` fuera de formato nunca es legítimo (el emisor produce siempre base64), así que servir la página solo enmascara errores y agranda la superficie.
   - Elección: rechazar con la respuesta de error estándar del módulo (mismo camino que ya usan los `909000` del propio controlador), observable como "error, sin HTML".
3. **Formato estricto esperado** (asunción material, registrada aquí): `recovery_hash` = cadena base64 (alfabeto `[A-Za-z0-9+/=_-]`, longitud acotada p. ej. ≤ 512), tal y como la emite `loginMailer` con `base64_encode(json_encode([...]))`; `code` = 6 dígitos numéricos (ya validado así en `Reset`); `role` = entero 1-5 (ya validado). Si el emisor cambia de formato, hay que actualizar la validación y los escenarios de spec.
4. **Se conserva `strip_tags()` como capa adicional, pero no cuenta como control**: la seguridad no depende de él. Se registra para no "limpiarlo" sin sustituirlo por nada.

## Risks / Trade-offs

- [Enlaces ya emitidos con `+`/`=` de base64 en el parámetro] → la validación de alfabeto los acepta; añadir test de round-trip con un enlace generado como lo genera `loginMailer`.
- [El escape codifica el `href` y el navegador podría percibir un enlace distinto] → el test de "enlace legítimo navegable" fija que el atributo sigue apuntando a `SITE_URL/CMS_URL/access/reset?h=...`; si hace falta, codificar el parámetro al construir la URL (percent-encoding) en lugar de dejarlo al escapado.
- [Otras plantillas del módulo `mailings` heredan el mismo escape y muestran entidades literalmente] → el módulo solo sirve `login_recovery.tpl`/`index.tpl` con textos fijos; revisar la salida de esas plantillas en el test de integración.

## Migration Plan

Sin migración de datos. Despliegue en dos pasos: primero el cambio (escape + validación), que es compatible con los enlaces en vuelo; rollback = revertir el commit, no deja estado persistido.

## Open Questions

- _(ninguno — el formato estricto y el rechazo 4xx se decidieron arriba; si negocio requiere otra cosa, ajustar los escenarios de spec antes de implementar)_
