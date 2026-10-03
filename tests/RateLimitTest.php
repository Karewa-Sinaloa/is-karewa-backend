<?php

require_once __DIR__ . '/Support/OrTestCase.php';
require_once CORE_PATH . 'helpers/rate_limit.php';

final class RateLimitTest extends OrTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo->exec(
            'CREATE TABLE rate_limits ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' client_key TEXT NOT NULL,'
            . ' endpoint TEXT NOT NULL,'
            . ' window_start INTEGER NOT NULL,'
            . ' count INTEGER NOT NULL DEFAULT 0,'
            . ' UNIQUE (client_key, endpoint, window_start)'
            . ')'
        );

        global $_config;
        $_config = (object) [
            'rate_limit' => (object) [
                'enabled'         => true,
                'window'          => 60,
                'default_limit'   => 100,
                'sensitive_limit' => 5,
                'sensitive'       => ['access'],
                'endpoints'       => [],
                'trusted_proxy'   => '',
                'proxy_header'    => '',
            ],
        ];
    }

    public function testRepeatedCallsIncrementTheSameRow(): void
    {
        $ip = '198.51.100.10';
        $endpoint = 'access:store';

        $this->assertSame(1, RateLimit::Check($ip, $endpoint)['count']);
        $this->assertSame(2, RateLimit::Check($ip, $endpoint)['count']);
        $this->assertSame(3, RateLimit::Check($ip, $endpoint)['count']);

        $key = RateLimit::key($ip, $endpoint);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM rate_limits WHERE client_key = :k');
        $stmt->execute([':k' => $key]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function testKeySeparatesByEndpoint(): void
    {
        $ip = '198.51.100.11';

        $a = RateLimit::Check($ip, 'access:store');
        $b = RateLimit::Check($ip, 'materias:index');

        $this->assertSame(1, $a['count']);
        $this->assertSame(1, $b['count']);
        $this->assertNotSame(RateLimit::key($ip, 'access:store'), RateLimit::key($ip, 'materias:index'));
    }

    public function testKeySeparatesByClient(): void
    {
        $endpoint = 'access:store';

        $this->assertSame(1, RateLimit::Check('198.51.100.12', $endpoint)['count']);
        $this->assertSame(1, RateLimit::Check('198.51.100.13', $endpoint)['count']);
    }

    public function testDecisionAllowsAtLimitAndRejectsAtLimitPlusOne(): void
    {
        $ip = '198.51.100.14';
        $endpoint = 'materias:index';
        $limit = 3;

        for ($i = 1; $i <= $limit; $i++) {
            $result = RateLimit::Check($ip, $endpoint, $limit);
            $this->assertTrue($result['allowed'], "Request {$i} of {$limit} should be allowed");
            $this->assertSame($limit - $i, $result['remaining']);
        }

        $over = RateLimit::Check($ip, $endpoint, $limit);
        $this->assertFalse($over['allowed']);
        $this->assertSame(0, $over['remaining']);
        $this->assertGreaterThan(0, $over['retry_after']);
    }

    public function testSensitiveEndpointUsesStricterLimit(): void
    {
        $this->assertSame(5, RateLimit::limitFor('access:store'));
        $this->assertSame(100, RateLimit::limitFor('materias:index'));
    }

    public function testPerEndpointOverrideWins(): void
    {
        global $_config;
        $_config->rate_limit->endpoints = ['materias:index' => 7];
        $this->assertSame(7, RateLimit::limitFor('materias:index'));
    }

    public function testWindowResetStartsANewCounter(): void
    {
        $ip = '198.51.100.15';
        $endpoint = 'access:store';
        $key = RateLimit::key($ip, $endpoint);

        RateLimit::Check($ip, $endpoint, 100);

        $oldWindowStart = time() - 7200;
        $stmt = $this->pdo->prepare('UPDATE rate_limits SET window_start = :w WHERE client_key = :k');
        $stmt->execute([':w' => $oldWindowStart, ':k' => $key]);

        $fresh = RateLimit::Check($ip, $endpoint, 100);
        $this->assertSame(1, $fresh['count']);
    }

    public function testClientIpUsesConnectionAddressByDefault(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.99';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);

        $this->assertSame('203.0.113.99', RateLimit::clientIp());
    }

    public function testClientIpHonorsTrustedProxyHeader(): void
    {
        global $_config;
        $_config->rate_limit->trusted_proxy = '10.0.0.0/8';
        $_config->rate_limit->proxy_header = 'HTTP_X_FORWARDED_FOR';

        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50, 10.0.0.5';
        $this->assertSame('203.0.113.50', RateLimit::clientIp());

        $_SERVER['REMOTE_ADDR'] = '198.51.100.1';
        $this->assertSame('198.51.100.1', RateLimit::clientIp());

        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    public function testClientIpUsesCloudflareHeaderFromTrustedEdge(): void
    {
        global $_config;
        $_config->rate_limit->cloudflare = true;
        $_config->rate_limit->trusted_proxies = ['173.245.48.0/20'];

        $_SERVER['REMOTE_ADDR'] = '173.245.48.10';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.77';
        $this->assertSame('203.0.113.77', RateLimit::clientIp());

        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
    }

    public function testClientIpIgnoresCloudflareHeaderFromUntrustedPeer(): void
    {
        global $_config;
        $_config->rate_limit->cloudflare = true;
        $_config->rate_limit->trusted_proxies = ['173.245.48.0/20'];

        $_SERVER['REMOTE_ADDR'] = '198.51.100.1';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.77';
        $this->assertSame('198.51.100.1', RateLimit::clientIp());

        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
    }

    public function testClientIpMatchesCloudflareIpv6Range(): void
    {
        global $_config;
        $_config->rate_limit->cloudflare = true;
        $_config->rate_limit->trusted_proxies = ['2606:4700::/32'];

        $_SERVER['REMOTE_ADDR'] = '2606:4700:3033::ac43:1234';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '2001:db8::1';
        $this->assertSame('2001:db8::1', RateLimit::clientIp());

        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
    }

    public function testClientIpIgnoresInvalidCloudflareHeader(): void
    {
        global $_config;
        $_config->rate_limit->cloudflare = true;
        $_config->rate_limit->trusted_proxies = ['173.245.48.0/20'];

        $_SERVER['REMOTE_ADDR'] = '173.245.48.10';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = 'not-an-ip';
        $this->assertSame('173.245.48.10', RateLimit::clientIp());

        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
    }

    public function testEnforceRejectsAboveLimit(): void
    {
        $ip = '198.51.100.20';
        $endpoint = 'materias:index';

        $last = null;
        for ($i = 0; $i <= 3; $i++) {
            $last = RateLimit::Check($ip, $endpoint, 3);
        }

        $this->assertFalse($last['allowed']);
        $this->assertSame(0, $last['remaining']);
        $this->assertGreaterThan(0, $last['retry_after']);
    }
}
