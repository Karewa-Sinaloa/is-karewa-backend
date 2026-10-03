<?php

use PHPUnit\Framework\TestCase;

/**
 * Enforcement tests run the reject path in a subprocess because
 * ApiResponse::Set() terminates the request with die(). Each scenario runs a
 * small script against the in-memory SQLite harness and asserts the emitted
 * HTTP status, Retry-After header, and response code, proving the endpoint
 * body is not executed once the limit is exceeded.
 */
final class RateLimitEnforcementTest extends TestCase
{
    private function runScenario(string $script): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rl_') . '.php';
        file_put_contents($tmp, "<?php\n" . $script);

        $vendor = dirname(CORE_PATH) . '/../vendor';
        $vendor = realpath($vendor) ?: $vendor;
        $cmd = 'RL_VENDOR=' . escapeshellarg($vendor) . ' '
             . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1';
        $output = shell_exec($cmd);
        @unlink($tmp);

        $lines = array_values(array_filter(array_map('trim', explode("\n", (string) $output)), fn ($l) => $l !== ''));
        $json = null;
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }
        return ['raw' => (string) $output, 'json' => $json];
    }

    /**
     * Self-contained child bootstrap: define the constants the app files need,
     * build an in-memory SQLite connection through the DB seam, create the
     * counter table, and install a deterministic rate_limit config.
     */
    private function harness(): string
    {
        $core = CORE_PATH;
        $codes = CORE_PATH . 'config/api_codes.yml';
        return <<<PHP
define('CORE_PATH', '{$core}');
define('MODULE', 'access');
define('REQUEST_TYPE', 'store');
define('IDENTIFIER_UID', 'test-uid');
define('ERROR_LOG_FILE', '/tmp/rl_error.log');
define('DEBUG_LOG_FILE', '/tmp/rl_debug.log');
define('DEBUG_LOG_PATH', '/tmp');
require_once getenv('RL_VENDOR') . '/autoload.php';
require_once '{$core}helpers/log.manager.php';
require_once '{$core}helpers/custom_exceptions.php';
require_once '{$core}model/conexion.php';
require_once '{$core}helpers/rate_limit.php';
require_once '{$core}helpers/api_response.php';

\$pdo = new PDO('sqlite::memory:');
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
\$pdo->exec('CREATE TABLE rate_limits (id INTEGER PRIMARY KEY AUTOINCREMENT, client_key TEXT NOT NULL, endpoint TEXT NOT NULL, window_start INTEGER NOT NULL, count INTEGER NOT NULL DEFAULT 0, UNIQUE (client_key, endpoint, window_start))');
App\\Model\\DB::setConnection(\$pdo);
App\\Model\\DB::setPrefix('');

\$_SERVER['REMOTE_ADDR'] = '198.51.100.99';
\$_config = (object) [
  'rate_limit' => (object) [
    'enabled' => true, 'window' => 60, 'default_limit' => 100,
    'sensitive_limit' => 2, 'sensitive' => ['access'], 'endpoints' => [],
    'trusted_proxy' => '', 'proxy_header' => '',
  ],
];
PHP;
    }

    public function testOverLimitRequestReturns429WithRetryAfterAndSkipsEndpoint(): void
    {
        $script = $this->harness() . <<<'PHP'

$ip = '198.51.100.42';
$endpoint = 'access:store';

RateLimit::Check($ip, $endpoint); // 1
RateLimit::Check($ip, $endpoint); // 2

$reached = false;
RateLimit::Enforce(['ip' => $ip, 'endpoint' => $endpoint]);
$reached = true;

echo "not_rejected";
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Expected a JSON response from the reject path. Raw: ' . $result['raw']);
        $this->assertSame(429, $result['json']['http_code']);
        $this->assertSame('APP_RATE_LIMIT_EXCEEDED', $result['json']['code']);
        $this->assertArrayHasKey('retry_after', $result['json']);
        $this->assertStringNotContainsString('not_rejected', $result['raw'], 'Enforcement must terminate before the endpoint runs');
    }

    public function testUnderLimitRequestProceeds(): void
    {
        $script = $this->harness() . <<<'PHP'

$result = RateLimit::Enforce(['ip' => '198.51.100.43', 'endpoint' => 'materias:index']);
echo json_encode(['allowed' => $result['allowed'], 'http_code' => 200]);
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertTrue($result['json']['allowed']);
        $this->assertSame(200, $result['json']['http_code']);
    }
}
