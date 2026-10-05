<?php

use PHPUnit\Framework\TestCase;

/**
 * Covers the re-enabled alternative hash authentication branch of
 * ModuleHandler: a method that declares a hash payload grants access when the
 * presented token is valid, without a session token, and denies access when the
 * token is invalid or missing. The denial path terminates through
 * ApiResponse::Set(), so scenarios run in a subprocess (same technique as
 * SessionSetTest/AccessLogoutTest).
 */
final class ModuleHashAuthTest extends TestCase
{
    private function runScenario(string $script): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mha_') . '.php';
        file_put_contents($tmp, "<?php\n" . $script);

        $vendor = dirname(CORE_PATH) . '/../vendor';
        $vendor = realpath($vendor) ?: $vendor;
        $cmd = 'MHA_VENDOR=' . escapeshellarg($vendor) . ' '
             . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1';
        $output = (string) shell_exec($cmd);
        @unlink($tmp);

        $json = null;
        foreach ($this->extractJsonObjects($output) as $decoded) {
            if (array_key_exists('http_code', $decoded)) {
                $json = $decoded;
            } elseif (($decoded['marker'] ?? false) && $json === null) {
                $json = $decoded;
            }
        }
        return ['raw' => $output, 'json' => $json];
    }

    private function extractJsonObjects(string $raw): array
    {
        $objects = [];
        $depth   = 0;
        $start   = null;
        $len     = strlen($raw);
        for ($i = 0; $i < $len; $i++) {
            $ch = $raw[$i];
            if ($ch === '{') {
                if ($depth === 0) {
                    $start = $i;
                }
                $depth++;
            } elseif ($ch === '}' && $depth > 0) {
                $depth--;
                if ($depth === 0 && $start !== null) {
                    $decoded = json_decode(substr($raw, $start, $i - $start + 1), true);
                    if (is_array($decoded)) {
                        $objects[] = $decoded;
                    }
                    $start = null;
                }
            }
        }
        return $objects;
    }

    private function harness(): string
    {
        $core = CORE_PATH;
        return <<<PHP
define('CORE_PATH', '{$core}');
define('MODULE', 'testmodule');
define('IDENTIFIER_UID', 'test-uid');
define('HASH_AUTH_PASS', 'module-hash-auth-secret');
define('REQUEST_TYPE', 'index');
define('ERROR_LOG_FILE', '/tmp/mha_error.log');
define('DEBUG_LOG_FILE', '/tmp/mha_debug.log');
define('DEBUG_LOG_PATH', '/tmp');
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once getenv('MHA_VENDOR') . '/autoload.php';
require_once '{$core}helpers/custom_exceptions.php';
require_once '{$core}helpers/api_response.php';
require_once '{$core}auth/hash.auth.php';
require_once '{$core}auth/module.access.controller.php';

function error_logs(array \$data, string \$file = ''): void {}

class HashModuleProbe {
    public bool \$ran = false;
    public function index() { \$this->ran = true; }
}
PHP;
    }

    public function testValidHashGrantsAccessWithoutSessionToken(): void
    {
        $script = $this->harness() . <<<'PHP'

$token = App\Auth\HashAuth::Create(['id' => 7]);
$_SERVER['HTTP_X_HASH_AUTH'] = $token;
$probe = new HashModuleProbe();
App\Auth\ModuleHandler::Validate(['index' => [true, [], ['id' => 7]]], $probe);
echo json_encode([
    'marker'        => true,
    'ran'           => $probe->ran,
    'authenticated' => defined('AUTHENTICATED') && AUTHENTICATED,
]);
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertArrayNotHasKey('http_code', $result['json'], 'Access must not be rejected');
        $this->assertTrue($result['json']['ran'], 'The target method runs when the hash is valid');
        $this->assertTrue($result['json']['authenticated'], 'AUTHENTICATED is defined as true');
    }

    public function testInvalidHashDeniesAccess(): void
    {
        $script = $this->harness() . <<<'PHP'

$_SERVER['HTTP_X_HASH_AUTH'] = 'not-a-valid-token';
$probe = new HashModuleProbe();
App\Auth\ModuleHandler::Validate(['index' => [true, [], ['id' => 7]]], $probe);
echo json_encode(['marker' => true, 'ran' => $probe->ran]);
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertSame(401, $result['json']['http_code']);
        $this->assertArrayNotHasKey('ran', $result['json'], 'The target method must not run');
    }

    public function testMissingHashDeniesAccess(): void
    {
        $script = $this->harness() . <<<'PHP'

unset($_SERVER['HTTP_X_HASH_AUTH'], $_GET['_key']);
$probe = new HashModuleProbe();
App\Auth\ModuleHandler::Validate(['index' => [true, [], ['id' => 7]]], $probe);
echo json_encode(['marker' => true, 'ran' => $probe->ran]);
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertSame(401, $result['json']['http_code']);
        $this->assertArrayNotHasKey('ran', $result['json'], 'The target method must not run');
    }
}
