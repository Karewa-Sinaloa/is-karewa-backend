<?php

use App\Model\DB;

/**
 * Fixed-window rate limiter backed by the rate_limits counter table.
 *
 * The limiter counts requests per client IP and per resolved endpoint. It is
 * deliberately free of side effects on output: RateLimit::Check() only reports
 * whether the request is allowed and how long until the window resets, so it can
 * be unit tested without touching headers or terminating the request.
 */
abstract class RateLimit
{
    /**
     * Resolve the real client address.
     *
     * The API is served behind Cloudflare, so the connection address is a
     * Cloudflare edge rather than the user. The client identity is taken from
     * the proxy-provided client-IP header, but only when the peer itself is a
     * trusted proxy, so an arbitrary caller cannot spoof its identity by
     * sending the header directly.
     */
    public static function clientIp(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $config = self::config();

        $header = '';
        $trusted = [];

        if (!empty($config['cloudflare'])) {
            $header  = $config['client_ip_header'] ?? 'HTTP_CF_CONNECTING_IP';
            $trusted = $config['trusted_proxies'] ?? [];
        } else {
            $header  = $config['proxy_header'] ?? '';
            $trusted = $config['trusted_proxy'] ?? '';
        }

        if ($header !== '' && self::isTrustedProxy($remote, $trusted)) {
            $candidate = self::headerClientIp((string) ($_SERVER[$header] ?? ''));
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return $remote;
    }

    /**
     * Extract a single valid client address from a proxy header value. Values
     * like CF-Connecting-IP hold one address; list-valued headers are reduced
     * to the first entry.
     */
    private static function headerClientIp(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $first = trim(explode(',', $value)[0]);
        return filter_var($first, FILTER_VALIDATE_IP) ? $first : null;
    }

    /**
     * Build the lookup key for a client and endpoint.
     */
    public static function key(string $ip, string $endpoint): string
    {
        return hash('sha256', $ip . '|' . $endpoint);
    }

    /**
     * Normalize the resolved module and request surface into a bucket name.
     */
    public static function endpoint(): string
    {
        $module   = defined('MODULE') ? MODULE : '';
        $surface  = defined('REQUEST_TYPE') ? REQUEST_TYPE : '';
        $endpoint = trim(($module !== '' ? $module : 'unknown') . ':' . $surface, ':');
        return $endpoint === '' ? 'unknown' : $endpoint;
    }

    /**
     * Fixed-window decision.
     *
     * @return array{allowed: bool, count: int, limit: int, remaining: int, retry_after: int}
     */
    public static function Check(string $ip, string $endpoint, ?int $limit = null): array
    {
        $config    = self::config();
        $window    = (int) ($config['window'] ?? 60);
        $window    = $window > 0 ? $window : 60;
        $limit     = $limit ?? self::limitFor($endpoint);
        $now       = time();
        $windowStart = $now - ($now % $window);

        $count = self::increment($ip, $endpoint, $windowStart, $window);

        $allowed   = $count <= $limit;
        $remaining = max(0, $limit - $count);
        $retry     = ($windowStart + $window) - $now;

        return [
            'allowed'     => $allowed,
            'count'       => $count,
            'limit'       => $limit,
            'remaining'   => $remaining,
            'retry_after' => $retry > 0 ? $retry : $window,
        ];
    }

    /**
     * Resolve the limit for an endpoint, applying per-endpoint overrides and a
     * stricter limit for sensitive (credential-accepting / side-effecting) ones.
     */
    public static function limitFor(string $endpoint): int
    {
        $config = self::config();

        foreach (($config['endpoints'] ?? []) as $pattern => $value) {
            if (self::matches($pattern, $endpoint)) {
                return (int) $value;
            }
        }

        $sensitive = $config['sensitive'] ?? [];
        if (self::isSensitive($endpoint, $sensitive)) {
            return (int) ($config['sensitive_limit'] ?? 5);
        }

        return (int) ($config['default_limit'] ?? 120);
    }

    /**
     * Atomically bump the counter for the current window and prune old rows.
     */
    private static function increment(string $ip, string $endpoint, int $windowStart, int $window): int
    {
        $db     = DB::connection();
        $table  = DB::prefix() . 'rate_limits';
        $key    = self::key($ip, $endpoint);

        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $sql = 'INSERT INTO ' . $table . ' (client_key, endpoint, window_start, count)'
                 . ' VALUES (:client_key, :endpoint, :window_start, 1)'
                 . ' ON CONFLICT (client_key, endpoint, window_start) DO UPDATE SET count = count + 1';
        } else {
            $sql = 'INSERT INTO ' . $table . ' (client_key, endpoint, window_start, count)'
                 . ' VALUES (:client_key, :endpoint, :window_start, 1)'
                 . ' ON DUPLICATE KEY UPDATE count = count + 1';
        }

        try {
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':client_key', $key);
            $stmt->bindValue(':endpoint', $endpoint);
            $stmt->bindValue(':window_start', $windowStart, PDO::PARAM_INT);
            $stmt->execute();

            self::prune($db, $table, $windowStart);

            $read = $db->prepare('SELECT count FROM ' . $table . ' WHERE client_key = :client_key AND endpoint = :endpoint AND window_start = :window_start');
            $read->bindValue(':client_key', $key);
            $read->bindValue(':endpoint', $endpoint);
            $read->bindValue(':window_start', $windowStart, PDO::PARAM_INT);
            $read->execute();
            $count = $read->fetchColumn();
        } catch (\PDOException $e) {
            throw new \AppException('Rate limit counter error: ' . $e->getMessage(), 902000);
        }

        return (int) $count;
    }

    /**
     * Remove counters that belong to windows older than the active one.
     */
    private static function prune(PDO $db, string $table, int $windowStart): void
    {
        $stmt = $db->prepare('DELETE FROM ' . $table . ' WHERE window_start < :window_start');
        $stmt->bindValue(':window_start', $windowStart, PDO::PARAM_INT);
        $stmt->execute();
    }

    private static function isSensitive(string $endpoint, array $sensitive): bool
    {
        foreach ($sensitive as $module) {
            if (strcasecmp($module, $endpoint) === 0 || str_starts_with(strtolower($endpoint), strtolower($module) . ':')) {
                return true;
            }
        }
        return false;
    }

    private static function matches(string $pattern, string $endpoint): bool
    {
        if ($pattern === $endpoint) {
            return true;
        }
        if (str_contains($pattern, '*')) {
            $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
            return (bool) preg_match($regex, $endpoint);
        }
        return str_starts_with(strtolower($endpoint), strtolower($pattern) . ':');
    }

    /**
     * @param string|array<int, string> $trusted one or more proxy addresses or CIDR blocks
     */
    private static function isTrustedProxy(string $remote, string|array $trusted): bool
    {
        if (is_string($trusted)) {
            $trusted = array_filter(array_map('trim', explode(',', $trusted)));
        }

        foreach ($trusted as $entry) {
            $entry = trim((string) $entry);
            if ($entry === '') {
                continue;
            }
            if (str_contains($entry, '/')) {
                if (self::inCidr($remote, $entry)) {
                    return true;
                }
            } elseif ($entry === $remote) {
                return true;
            }
        }
        return false;
    }

    /**
     * Match an IPv4 or IPv6 address against a CIDR block.
     */
    private static function inCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
        if ($bits === null) {
            return false;
        }
        $bits = (int) $bits;

        $ipBin     = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $length = strlen($ipBin) * 8;
        if ($bits < 0 || $bits > $length) {
            return false;
        }

        $wholeBytes = intdiv($bits, 8);
        $remainder  = $bits % 8;

        if ($wholeBytes > 0 && substr($ipBin, 0, $wholeBytes) !== substr($subnetBin, 0, $wholeBytes)) {
            return false;
        }
        if ($remainder === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $remainder) & 0xFF;
        return (ord($ipBin[$wholeBytes]) & $mask) === (ord($subnetBin[$wholeBytes]) & $mask);
    }

    /**
     * Read the rate_limit section from the parsed application config.
     */
    private static function config(): array
    {
        global $_config;
        if (!isset($_config->rate_limit)) {
            return [];
        }
        return (array) $_config->rate_limit;
    }

    public static function allows(string $ip, string $endpoint, ?int $limit = null): bool
    {
        return self::Check($ip, $endpoint, $limit)['allowed'];
    }

    public static function retryAfter(string $ip, string $endpoint, ?int $limit = null): int
    {
        return self::Check($ip, $endpoint, $limit)['retry_after'];
    }

    /**
     * Enforce the limit for the current request. Returns normally when the
     * request is allowed; otherwise emits the rate-limit response with a
     * Retry-After header and terminates through ApiResponse::Set().
     *
     * @param array{ip?: string, endpoint?: string, limit?: int} $overrides
     * @return array{allowed: bool, count: int, limit: int, remaining: int, retry_after: int}
     */
    public static function Enforce(array $overrides = []): array
    {
        $config = self::config();
        if (array_key_exists('enabled', $config) && !$config['enabled']) {
            return [
                'allowed'     => true,
                'count'       => 0,
                'limit'       => self::limitFor(self::endpoint()),
                'remaining'   => 0,
                'retry_after' => 0,
            ];
        }

        $ip       = $overrides['ip'] ?? self::clientIp();
        $endpoint = $overrides['endpoint'] ?? self::endpoint();
        $limit    = $overrides['limit'] ?? null;

        $result = self::Check($ip, $endpoint, $limit);

        if (!$result['allowed']) {
            self::reject($result);
        }

        return $result;
    }

    /**
     * Emit the dedicated rate-limit response with Retry-After and stop.
     */
    public static function reject(array $result): void
    {
        \App\Helpers\ApiResponse::SetHeader('Retry-After', (string) $result['retry_after']);
        \App\Helpers\ApiResponse::Set('429000', [
            'retry_after' => $result['retry_after'],
        ]);
    }
}
