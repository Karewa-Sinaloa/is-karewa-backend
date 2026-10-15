# Proposal

## Why

La auditoría OWASP 2026-10-14 confirma el hallazgo **F-016 (Bajo)**: `httpdocs/probe4.php` es un script de diagnóstico en la raíz web pública que devuelve `REMOTE_ADDR` y `HTTP_CF_CONNECTING_IP` (`echo json_encode(['remote'=>..., 'cf'=>...]);`), revelando al atacante la topología de red/proxies y permitiéndole verificar si su IP llega al origen. Es un residuo de depuración en el árbol publicable (aunque en el stack nginx solo `api.php` llega a PHP-FPM: `location ~ \.php$ { return 404; }`).

## What Changes

- Eliminar `httpdocs/probe4.php` del repositorio y de los despliegues.
- La información diagnóstica de la petición (IPs remotas, cabeceras de proxy) queda disponible solo vía endpoints internos de healthcheck o endpoints autenticados, nunca mediante un script público sin autenticación.
- Guardia contra reintroducción: una verificación automatizada (test/CI) falla si aparece un script de diagnóstico en la raíz web o si un script público revela IPs/cabeceras de proxy.
- **No se marca BREAKING**: un grep en todo el repositorio no encuentra ninguna referencia a `probe4.php` más allá del informe de auditoría; la auditoría lo clasifica como residuo de depuración sin consumidores, y el servidor de despliegue (nginx) ya no lo ejecuta. Si algún monitor externo usara la URL, debe migrarse al healthcheck interno `/healthz` (única sonda prevista).

## Capabilities

### New Capabilities

- _(ninguna)_

### Modified Capabilities

- `architecture`: se añade un requisito sobre el contenido de la raíz web publicada (solo punto(s) de entrada de la aplicación, sin scripts de diagnóstico/probe) y sobre la exposición de información diagnóstica.

## Impact

- Código: `httpdocs/probe4.php` (eliminado), test nuevo de higiene de la raíz web (p. ej. `tests/WebRootHygieneTest.php`), opcionalmente `.github/` si se refuerza con un paso de CI equivalente.
- API: la ruta `/probe4.php` pasa a responder 404 en todos los servidores (antes potencialmente ejecutable en despliegues Apache).
- Docs: mención del healthcheck `/healthz` como única sonda de diagnóstico (p. ej. `SECURITY.md`).
