# AGENTS.md — Monitor Karewa Backend

## Quick Commands

```bash
# Install dependencies (run from app/core/third_party/, NOT root)
cd app/core/third_party && composer install

# Run all tests
./vendor/bin/phpunit ../../tests

# Run single test
./vendor/bin/phpunit ../../tests/ValidationTest.php
```

## Critical Setup

- **Config**: `app/config.yml` is gitignored. Copy from example or decrypt `app/config.yml.secret` using git-secret.
- **Database**: Import schema from `database/karewa_monitor.sql` (not in repo — obtain separately).
- **Vendor**: Dependencies live in `app/core/third_party/vendor/`, not project root.

## Key References

- Full architecture docs: [`.github/copilot-instructions.md`](.github/copilot-instructions.md)
- API response codes: `app/core/config/api_codes.yml`

## Agent-Specific Gotchas

- PHP 8.4+ required (not 8.0)
- JWT uses RS256 with keys in `app/.keys/` (also gitignored)
- All responses go through `ApiResponse::Set()` which calls `die()` — no code executes after it
- Module directory names use kebab-case (e.g., `unidades-administrativas`)

## Development

- **Tunnel**: Use Cloudflared Tunnel for API testing (request access as member in Cloudflare account)

## Dependencies

Key packages: `firebase/php-jwt`, `phpmailer/phpmailer`, `symfony/yaml`, `curl/curl`, `ramsey/uuid`, `smarty/smarty`, `phpunit/phpunit` (dev)