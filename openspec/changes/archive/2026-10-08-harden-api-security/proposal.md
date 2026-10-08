# Proposal

## Why

The API has no rate limiting, so login, recovery, and search endpoints can be brute-forced or flooded. Its CORS logic is both inverted and unsafe: when CORS is "active" it sends `Access-Control-Allow-Origin: *` together with `Access-Control-Allow-Credentials: true`, and otherwise it terminates the request with a bare JSON body and no HTTP status. It also emits no standard security headers. These are the highest-impact gaps for an internet-facing public API.

## What Changes

- Requests are rate limited per client IP and endpoint, with stricter limits for sensitive endpoints, backed by a MySQL counter store.
- Exceeding a limit returns a dedicated `429` response code with a `Retry-After` header.
- CORS is corrected: origin allow-listing is explicit, `*` is never combined with credentials, and a rejected origin produces a proper HTTP error response instead of a bare body.
- Standard security headers are emitted (for example `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and HSTS when HTTPS is enabled).
- A new configuration section drives rate-limit windows, per-endpoint limits, and security headers.
- **BREAKING**: clients that send requests to a blocked origin, or that exceed the limits, now receive explicit error responses; sensitive endpoints become limited.

## Capabilities

### New Capabilities
- `api-rate-limiting`: how requests are counted, limited per IP/endpoint, and rejected with `429`.
- `api-security-headers`: the CORS policy and the security response headers the API emits.

### Modified Capabilities
- Ninguna.

## Impact

- `app/core/config/base.php` — CORS correction and security headers.
- `app/core/bootstrap/init.php` / `midelware.php` — invoke the rate-limit check early in the request.
- New `app/core/helpers/rate_limit.php` and a counter table/migration.
- `app/core/config/api_codes.yml` — new `429` code.
- `app/config.yml` — new rate-limit and headers configuration.
- Affects every endpoint.
