# Design

## Context

La raíz web publicada (`httpdocs/`) contiene hoy: `api.php` (punto de entrada real, único que llega a PHP-FPM en nginx), `index.php` (placeholder de 0 bytes), `api/docs/` (documentación), `errors/404.html`, `statics/` (vacío) y `probe4.php`. Este último responde `{"remote": ..., "cf": ...}` a partir de `REMOTE_ADDR`/`HTTP_CF_CONNECTING_IP`. nginx ya bloquea la ejecución de cualquier `.php` distinto de `api.php` (`location ~ \.php$ { return 404; }`) y expone un healthcheck interno `location = /healthz { return 200 "ok"; }` (`docker/nginx/default.conf`), pero en despliegues Apache el `.htaccess` solo garantiza `<Files api.php>`, así que el archivo es un residuo ejecutable según configuración. Ver hallazgo F-016 en `docs/auditoria-seguridad/auditoria-owasp-2026-10-14.md`; motivación en `proposal.md` — Why.

## Goals / Non-Goals

- **Goals**: que la raíz publicada no contenga scripts de diagnóstico, que la información de topología de red solo salga por vías internas/autenticadas, y que la regla no pueda reintroducirse silenciosamente.
- **Non-Goals**: rediseñar el healthcheck `/healthz` (hoy responde solo `ok`), añadir un endpoint de diagnóstico autenticado nuevo (no hay requerimiento actual), auditar cabeceras de seguridad/CSP (otro hallazgo), ni tocar la configuración de nginx más allá de lo que exige el borrado.

## Decisions

1. **Borrado del archivo + verificación automatizada en la suite (test PHPUnit), no solo una regla de CI**.
   - *Alternativa A*: solo borrar el archivo — nada impide que vuelva en el próximo debug (la auditoría lo clasifica como residuo de depuración recurrente).
   - *Alternativa B*: solo una regla en CI (grep) — la suite local (`./vendor/bin/phpunit` / `make test`) no la ejecuta necesariamente y el developer no la ve.
   - Elección: test de higiene de la raíz web que fija la lista blanca de `.php` publicables y detecta cadenas de diagnóstico; es ejecutable con la suite habitual y puede copiarse luego a CI sin cambios.
2. **Lista blanca de la raíz web**: se permiten `api.php` y `index.php` (placeholder vacío, se conserva) más directorios `api/`, `docs/`, `errors/`, `statics/`. Cualquier `.php` nuevo en `httpdocs/` hace fallar el test salvo que se añada explícitamente a la lista (asunción material registrada aquí: `index.php` vacío se considera parte del despliegue actual; si se decide eliminar, actualizar la lista blanca en el mismo PR).
3. **BREAKING: no** (juicio registrado en `proposal.md`): grep en el repo → ninguna referencia a `probe4.php` fuera del informe de auditoría; nginx lo devuelve 404 ya hoy; el archivo no forma parte de ningún contrato documentado. Riesgo residual de consumidores externos desconocidos asumido y comunicado en la PR (migrar a `/healthz`).
4. **El requisito de spec cubre también el fondo** (información diagnóstica solo autenticada/interna): se verifica observando que ningún script público de la raíz emita `REMOTE_ADDR`/`HTTP_CF_CONNECTING_IP`/cabeceras de proxy, y documentando `/healthz` como sonda válida en `SECURITY.md`.

## Risks / Trade-offs

- [Alguien necesitaba `probe4.php` para verificar IP/Cloudflare] → `/healthz` confirma disponibilidad y el acceso log del servidor (o un endpoint autenticado futuro) da la topología; se documenta en `SECURITY.md`.
- [La lista blanca se vuelve rígida y rompe adds legítimos de assets PHP] → el test falla con mensaje claro indicando que amplíe la lista de forma explícita y revisada.
- [Despliegues Apache con `.htaccess` antiguo] → el borrado es compatible (no depende de la configuración del servidor); el requisito de spec cubre ambos servidores.

## Migration Plan

Despliegue sin migración: borrar el archivo y publicar (el despliegue sobreescribe la raíz). Si un despliegue conserva el archivo por no limpiar, regenerar la raíz desde el repo. Rollback: restaurar el archivo desde git (aunque no se recomienda).

## Open Questions

- _(ninguno — el juicio de BREAKING, la lista blanca y la verificación se decidieron arriba)_
