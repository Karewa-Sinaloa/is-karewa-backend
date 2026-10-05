# DEV Environment Manual (Docker)

## 1) Prerequisites
- Docker Engine 24+ and Docker Compose plugin 2.20+
- Git
- Free host ports (ejemplo): `8080` (nginx), `3307` (db host), `8025` (mailpit UI), `1025` (mailpit SMTP)
- Cloudflared CLI installed on host (for tunnel bootstrap):
  - https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/

## 2) Stack overview
- Backend: `php` (`php:8.4-fpm`)
- Reverse proxy: `nginx` (`nginx:1.26-alpine`) con rewrites equivalentes a `httpdocs/.htaccess` para `/api` y `/api/v5` (Nginx no procesa `.htaccess` directamente).
- Database: `db` (`mariadb:11.4`)
- Mail testing: `mailpit` (`axllent/mailpit:v1.21`)
- Tunnel (optional profile): `cloudflared` (`cloudflare/cloudflared:latest`)
- Network: single shared network `app_net`

## 3) First-time setup
1. Create local env file:
   ```bash
   cp .env.example .env
   ```
2. Set DB credentials in `.env`:
   - `DB_DATABASE`
   - `DB_USERNAME`
   - `DB_PASSWORD`
   - `DB_ROOT_PASSWORD`
3. Start main development services:
   ```bash
   docker compose up -d --build
   ```
4. Verify health:
   ```bash
   docker compose ps
   ```

## 4) Daily commands
- Up (main stack):
  ```bash
  docker compose up -d
  ```
- Down:
  ```bash
  docker compose down
  ```
- Logs all:
  ```bash
  docker compose logs -f
  ```
- Logs one service:
  ```bash
  docker compose logs -f nginx
  ```
- Status:
  ```bash
  docker compose ps
  ```
- Shell in php container:
  ```bash
  docker compose exec php bash
  ```

### Optional Makefile shortcuts
Use the provided `Makefile` to run common actions faster:
```bash
make help
make up
make down
make logs
make ps
make shell-php
make composer-install
make test
```

## 5) Common workflows

### Backend (PHP)
- Install dependencies in project path used by this repo:
  ```bash
  docker compose exec php bash -lc "composer install"
  ```
- Run tests:
  ```bash
  docker compose exec php bash -lc "./vendor/bin/phpunit"
  ```

### Database (MariaDB)
- Connect from desktop client:
  - Host: `127.0.0.1`
  - Port: `${DB_HOST_PORT}` (recommended `3307` when host already uses 3306)
  - Database/User/Password from `.env`

### Mail testing (Mailpit)
- UI: `http://localhost:${MAILPIT_UI_PORT}` (default `8025`)
- SMTP endpoint for app: `mailpit:${MAILPIT_SMTP_PORT}` inside Docker network
- The application targets Mailpit through the mail environment variables in `.env`
  (see `.env.example`): `MAIL_HOST`, `MAIL_PORT`, `MAIL_SECURITY`, `MAIL_USER`,
  `MAIL_PASSWORD`, `MAIL_AUTH`, `MAIL_FROM_EMAIL`, `MAIL_FROM_NAME`. They override the
  `mailing` section of `app/config.yml` for the `php` container.
- Existing `.env` files created before this feature must add these keys: the `php`
  service always passes them, so a missing key resolves to empty and blanks the mail
  settings. Copy the block from `.env.example` and restart the stack.
- Verify delivery: trigger a flow that sends mail (for example the login recovery)
  and open the Mailpit UI to read the captured message.

## 6) Optional profiles
- `cloudflared`: expose local API through Cloudflare Tunnel (named tunnel mode)

Start profile:
```bash
docker compose --profile cloudflared up -d
```

Stop profile:
```bash
docker compose --profile cloudflared down
```

Makefile shortcuts:
```bash
make tunnel-up
make tunnel-down
make tunnel-logs
```

## 7) Cloudflared instructions

Target service: reverse proxy `nginx` on `http://nginx:80`

### A) Existing tunnel path
If tunnel already exists, verify first:
```bash
cloudflared tunnel list
cloudflared tunnel info <my_tunnel>
```

Then set `.env` values:
- `CLOUDFLARED_TUNNEL_ID=<verified_uuid>`
- `CLOUDFLARED_HOSTNAME=kapi.chavodigital.com`
- `CLOUDFLARED_CREDENTIALS_FILE=/etc/cloudflared/<verified_uuid>.json`
- `CLOUDFLARED_HOST_CREDENTIALS_PATH=/absolute/host/path/<verified_uuid>.json`

Start tunnel profile:
```bash
docker compose --profile cloudflared up -d
```

### B) New tunnel creation path (`create_new`)
Use the requested tunnel name:
```bash
cloudflared tunnel login
cloudflared tunnel create karewa_api
cloudflared tunnel route dns karewa_api kapi.chavodigital.com
cloudflared tunnel list
```

Take generated tunnel UUID from output and map into `.env`:
- `CLOUDFLARED_TUNNEL_ID=<generated_uuid>`
- `CLOUDFLARED_HOSTNAME=kapi.chavodigital.com`
- `CLOUDFLARED_CREDENTIALS_FILE=/etc/cloudflared/<generated_uuid>.json`
- `CLOUDFLARED_HOST_CREDENTIALS_PATH=~/.cloudflared/<generated_uuid>.json`

Then start profile:
```bash
docker compose --profile cloudflared up -d --build
```

## 8) Troubleshooting quick fixes

### Service unhealthy
- Check logs:
  ```bash
  docker compose logs -f db
  docker compose logs -f php
  docker compose logs -f nginx
  ```

### Port conflict
- Change host ports in `.env`:
  - `NGINX_HOST_PORT`
  - `DB_HOST_PORT`
  - `MAILPIT_UI_PORT`
  - `MAILPIT_SMTP_PORT`

### Cloudflared credentials mismatch
- Ensure UUID and credentials file name match.
- Ensure host path exists and is absolute.
- Keep `CLOUDFLARED_TUNNEL_ID` and credentials filename aligned (same UUID suffix).

### Recovery for stale Docker network references
```bash
docker compose --profile cloudflared down
docker network prune -f
docker compose --profile cloudflared up -d --build
```

Equivalent Makefile target:
```bash
make network-recover
```

## 9) Decision matrix snapshot (resolved)
| Service | Decision |
|---|---|
| backend (php-fpm 8.4) | approved |
| frontend | rejected |
| proxy (nginx) | approved |
| db (mariadb) | approved |
| cache (redis) | rejected |
| worker | rejected |
| mail testing (mailpit) | approved |
| db admin (adminer) | rejected |
| cloudflared | approved |
