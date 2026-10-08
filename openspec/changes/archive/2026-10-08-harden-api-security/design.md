# Design

## Context

See `proposal.md - Why`.

`app/core/config/base.php` sets CORS headers unconditionally near request start. The current logic is inverted: `cors.active == true` sends wildcard `*` plus `Access-Control-Allow-Credentials: true`; otherwise it reads `$_SERVER['HTTP_ORIGIN']` and, on no match, `die(json_encode(...))` with no HTTP status. Every request flows through `app/core/bootstrap/init.php`, which requires the helpers before `app/core/bootstrap/methods.php` resolves `REQUEST_TYPE` and before `routes.php`/`modules.php` resolve the module path. `ApiResponse::Set()` is the single output path and reads codes from `app/core/config/api_codes.yml`. Module paths come from the resolved module name. The stack is PHP-FPM behind Nginx with a MySQL/MariaDB database already available.

## Goals / Non-Goals

**Goals:**
- Enforce per-IP/per-endpoint limits before endpoint behavior runs.
- Correct CORS so it is safe and returns proper HTTP errors.
- Add standard security headers.
- Keep all new behavior configurable and off by default only where safe to do so.

**Non-Goals:**
- Adding a new infrastructure service (no Redis) or a reverse-proxy dependency.
- Reworking authentication, JWT, or hash auth.
- Hardening uploads or adding CAPTCHA beyond what exists.
- Changing response envelope semantics other than adding the `429` code.

## Decisions

- **Store counters in MySQL.** Use a dedicated table keyed by a hash of client IP plus endpoint, with a window start and count. Chosen over Redis to avoid new infrastructure and over files to stay reliable across PHP-FPM workers. Alternative rejected: APCu, which is per-worker and unreliable.
- **Key by IP + resolved endpoint.** Resolve the endpoint from the module and request type after routing, so limits map to meaningful surfaces; apply stricter limits to credential endpoints by configuration. Alternative rejected: a single global IP limit, which cannot protect a specific sensitive endpoint.
- **Enforce early, after routing resolves the module.** The check runs once the endpoint is known but before `ModuleHandler::Validate()` and the controller method. Alternative rejected: enforcing at Nginx, which the application cannot portably configure and which cannot see module semantics.
- **Identify the client from the tunnel-provided header, keyed on the tunnel peer.** The API is served through the `cloudflared` tunnel, so the connection address is the tunnel's private container address, not the user, and every user shares it. Resolve the client from `CF-Connecting-IP` (a single, Cloudflare-set client address), honored only when the peer address is a configured trusted proxy (the tunnel/edge on the private network), and fall back to the connection address otherwise. Alternative rejected: `X-Forwarded-For`, which is a client-appendable list and spoofable; also rejected: Cloudflare's public edge ranges as the trust anchor, because the tunnel already terminated the connection locally so the peer is never a public Cloudflare edge; also rejected: using the connection address, which would throttle all users behind the tunnel as a single client.
- **Fixed-window counters with an atomic upsert.** One `INSERT ... ON DUPLICATE KEY UPDATE` bumps the count, keeping it simple and race-safe. Alternative rejected: sliding windows, which need more state than the threat warrants.
- **Add a `429` code and `Retry-After`.** The helper computes remaining window time and sets the header before responding through `ApiResponse::Set()`. Alternative rejected: reusing a generic code, which loses the HTTP semantics clients expect.
- **Correct CORS with an explicit allow-list.** When a request has an `Origin`, grant it only if listed; never pair a wildcard with credentials. A disallowed origin is rejected via `ApiResponse::Set()` so it carries a real status. A development mode may allow a wildcard but then must drop credentials. Alternative rejected: keeping the boolean and merely swapping branches, which leaves the wildcard/credentials hazard.
- **Emit security headers centrally in `base.php`.** Add nosniff, framing, and referrer-policy always, and HSTS when HTTPS is enabled. Alternative rejected: per-module headers, which would be inconsistent.

## Risks / Trade-offs

- Rate limiting can block legitimate bursts or shared-IP users → Mitigation: configurable limits/windows, and a documented tuning path.
- A MySQL round trip per request adds latency → Mitigation: a single indexed upsert; fine at this scale.
- Trusted-proxy handling can be misconfigured → Mitigation: default to the connection address and only honor forwarding when explicitly configured.
- Correcting CORS can break clients that relied on the permissive wildcard → Mitigation: mark it breaking, document the allow-list, and provide a development wildcard mode without credentials.
- Table growth from counters → Mitigation: prune rows outside the active window.
- Enforcing limits before auth means unauthenticated traffic is limited first → Accepted and desirable; limits are keyed by IP.

## Migration Plan

- Add the counter table and the `429` code; deploy the helper and the CORS/header changes together.
- Add the new configuration section with conservative defaults and an explicit allow-list taken from the current `cors.domains`.
- Enable limiting per environment; start permissive and tighten after observing traffic.
- Rollback: disable limiting via config and restore `base.php`; the counter table is inert when unused.

## Open Questions

- None blocking. Default limit values can be tuned during implementation without changing the spec.
