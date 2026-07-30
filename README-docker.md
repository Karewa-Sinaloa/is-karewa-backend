# Docker quick start

## Services
- `php`: PHP-FPM 8.4 for API execution.
- `nginx`: reverse proxy para PHP-FPM con rewrites equivalentes a `httpdocs/.htaccess` para `/api` y `/api/v5`.
- `db`: MariaDB 11.4 with named volume persistence.
- `mailpit`: local SMTP capture + UI for mail testing.
- `cloudflared` (profile `cloudflared`): named tunnel to expose local API.

## First run
1. Copy env file:
   ```bash
   cp .env.example .env
   ```
2. Fill DB credentials and Cloudflared placeholders in `.env`.
3. Start local stack:
   ```bash
   docker compose up -d --build
   ```

## Access
- API via Nginx: `http://localhost:8080/api` o `http://localhost:8080/api/v5/...`
- Mailpit UI: `http://localhost:8025`
- MariaDB (desktop client): `127.0.0.1:3307` (o el puerto definido en `DB_HOST_PORT`)

## Cloudflared (optional profile)
After completing tunnel creation and env values:
```bash
docker compose --profile cloudflared up -d
```

See `DEV_ENV_MANUAL.md` for full runbook and troubleshooting.

## Makefile shortcuts
Common commands are available through `Makefile`:
```bash
make help
make up
make down
make logs
make shell-php
make tunnel-up
```
