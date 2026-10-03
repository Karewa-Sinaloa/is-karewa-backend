# API Security Configuration

This document describes the API security controls configured in `app/config.yml`
and enforced in `app/core/config/base.php` and `app/core/helpers/rate_limit.php`.
The config keys below match the implementation.

## CORS allow-list

Cross-origin access is granted only to origins explicitly listed. A request that
carries an `Origin` not on the list is rejected with HTTP `403` and body
`{"message":"CORS policy: This origin is not allowed","code":"APP_CORS_ORIGIN_DENIED","http_code":403}`.

```yaml
cors:
  active: true
  wildcard: false          # development escape hatch (see below)
  domains:
    - https://kapp.chavodigital.com
    - https://chavodigital.com
```

| Key | Meaning |
| --- | --- |
| `cors.domains` | Origins granted access. The specific requesting origin is echoed, never `*`. |
| `cors.wildcard` | When `true`, any origin is allowed but **credentials are dropped** (`Access-Control-Allow-Origin: *` is never paired with `Access-Control-Allow-Credentials: true`). |

Rules enforced centrally in `base.php`:

- Only origins in `cors.domains` receive `Access-Control-Allow-Origin` plus
  `Access-Control-Allow-Credentials: true`.
- `*` is never combined with credentials.
- A disallowed origin produces a real HTTP error status, not a bare JSON body.

## Rate limiting

Requests are counted per client IP and per resolved endpoint over a fixed
window. Exceeding the limit returns HTTP `429` with code `APP_RATE_LIMIT_EXCEEDED`
and a `Retry-After` header.

```yaml
rate_limit:
  enabled: true            # set false to disable limiting (rollback path)
  window: 60               # window length in seconds
  default_limit: 120       # requests per window for general endpoints
  sensitive_limit: 10      # requests per window for sensitive endpoints
  sensitive:               # endpoints that accept credentials / cause side effects
    - access
    - users
    - mailings
  endpoints: {}            # per-endpoint overrides, exact or "module:*" glob
  trusted_proxy: ''        # IP or CIDR allowed to supply the client IP header
  proxy_header: ''         # e.g. HTTP_X_FORWARDED_FOR (only honored from trusted_proxy)
```

| Key | Meaning |
| --- | --- |
| `rate_limit.enabled` | Master switch. `false` disables enforcement entirely. |
| `rate_limit.window` | Fixed window length in seconds. |
| `rate_limit.default_limit` | Limit applied when no override or sensitivity matches. |
| `rate_limit.sensitive_limit` | Stricter limit for sensitive endpoints. |
| `rate_limit.sensitive` | Endpoint list (module name) that uses the stricter limit. |
| `rate_limit.endpoints` | Per-endpoint overrides; keys match `module:request_type`, exact or glob (`access:*`). |
| `rate_limit.trusted_proxy` | IP/CIDR allowed to set the forwarded client IP. Empty = use the connection address. |
| `rate_limit.proxy_header` | Server variable to read when the peer is trusted, e.g. `HTTP_X_FORWARDED_FOR`. |

The client identity comes from the connection address by default. A forwarded
header is only honored when the peer matches `trusted_proxy`, so an
unauthenticated client cannot bypass the limit by setting client-controlled
values.

Counters live in the `rate_limits` table (migration:
`resources/migrations/rate_limits.sql`); rows outside the active window are
pruned on each upsert.

## Security headers

Emitted centrally on every response:

| Header | Value | Config key |
| --- | --- | --- |
| `X-Content-Type-Options` | `nosniff` | (always) |
| `X-Frame-Options` | framing policy | `security.headers.frame_options` |
| `Referrer-Policy` | referrer policy | `security.headers.referrer_policy` |
| `Strict-Transport-Security` | `max-age=<hsts_max_age>; includeSubDomains` | `security.headers.hsts`, `security.headers.hsts_max_age` |

```yaml
security:
  headers:
    nosniff: true
    frame_options: DENY
    referrer_policy: no-referrer
    hsts: true
    hsts_max_age: 31536000
```

HSTS is only emitted when the site is served over HTTPS (or when `https: true`
is configured and `security.headers.hsts` is enabled).
