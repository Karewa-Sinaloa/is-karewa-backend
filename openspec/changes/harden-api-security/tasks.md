# Tasks

## 1. Rate-limit counter store

- [x] 1.1 Add a counter table (for example `rate_limits`) with a client key, endpoint, window start, count, and an index on the lookup key; add the migration/SQL under `resources/`. Verify it applies cleanly and the index exists.
- [x] 1.2 Add `app/core/helpers/rate_limit.php` that resolves the client IP (connection address, honoring a configured trusted proxy header), builds the IP+endpoint key, and atomically upserts the counter for the current window. Verify `php -l` passes and a repeated call increments the same row.
- [x] 1.3 Implement the decision: allow when under the configured limit, otherwise reject with remaining window seconds. Verify a unit test covers the boundary (limit, limit+1).
- [x] 1.4 Add `tests/RateLimitTest.php` using the in-memory database pattern to assert counting, key separation by endpoint, window reset, and the reject decision. Verify `./vendor/bin/phpunit tests/RateLimitTest.php` passes.

## 2. Enforce limits in the request lifecycle

- [x] 2.1 Invoke the rate-limit check in the request lifecycle once the endpoint is resolved and before `ModuleHandler::Validate()`. Verify `php -l` passes and the check runs for a real module path.
- [x] 2.2 Add the `429` code to `app/core/config/api_codes.yml` and have the rejection respond through `ApiResponse::Set()` with a `Retry-After` header. Verify a rejected request returns HTTP 429 with `Retry-After`.
- [x] 2.3 Add the rate-limit configuration section (windows, default limit, per-endpoint overrides, trusted proxy) to `app/config.yml`, with sensitive endpoints stricter. Verify changing a limit changes enforcement.
- [x] 2.4 Add `tests/RateLimitEnforcementTest.php` asserting an over-limit request to a sensitive endpoint returns 429 and does not execute the endpoint. Verify `./vendor/bin/phpunit tests/RateLimitEnforcementTest.php` passes.

## 3. Correct CORS and add security headers

- [x] 3.1 In `app/core/config/base.php`, replace the CORS logic with explicit allow-listing: grant only listed origins, never combine wildcard with credentials, and reject a disallowed origin with a proper HTTP status. Verify `php -l` passes and no path sends `*` together with `Access-Control-Allow-Credentials: true`.
- [x] 3.2 Add security headers (nosniff, framing policy, referrer policy, and HSTS when HTTPS) centrally in `base.php`. Verify a response includes all of them over HTTPS.
- [x] 3.3 Add `tests/CorsHeadersTest.php` asserting an allowed origin is granted, a disallowed origin is rejected with an error status, and the security headers are present. Verify `./vendor/bin/phpunit tests/CorsHeadersTest.php` passes.
- [x] 3.4 Document the CORS allow-list, rate-limit configuration, and security headers in the project docs. Verify the documented config keys match the implementation.

## 4. Integration verification

- [x] 4.1 Run the full suite with `./vendor/bin/phpunit` (or `make test`) and confirm all tests pass.
- [x] 4.2 With `make up`, confirm repeated requests to a sensitive endpoint eventually return 429 with `Retry-After`, an allowed-origin request succeeds, and a disallowed-origin request is rejected with an error status.

## 5. Real client identity behind the Cloudflare tunnel

- [x] 5.1 Update the rate-limit spec/design so the client identity is resolved from `CF-Connecting-IP` only for trusted proxy peers (the tunnel/private network), never from the connection address (which is the tunnel) and never from an untrusted peer.
- [x] 5.2 Resolve the client IP in `rate_limit.php` from a configurable `client_ip_header` when the peer matches `trusted_proxies`, with IPv4 and IPv6 CIDR matching, falling back to the connection address; add config keys (`cloudflare`, `client_ip_header`, `trusted_proxies`). Verify unit tests cover trusted/untrusted peers and IPv6.
- [x] 5.3 In `docker/nginx/default.conf`, rewrite `$remote_addr` from `CF-Connecting-IP` for trusted private peers via the `realip` module so PHP receives the real user address. Verify `nginx -t` passes.
- [x] 5.4 Verify end-to-end through the public endpoint (`https://kapi.chavodigital.com/api/v5`) that counters are keyed by the real user IP, not the Cloudflare edge. Document the trust model in `SECURITY.md`.
