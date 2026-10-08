<?php

use PHPUnit\Framework\TestCase;

/**
 * Guards the opportunistic authentication of ModuleHandler: a method declared
 * as anonymous still exposes the role of a valid token presented by the
 * caller, while an absent or corrupt token leaves the method running as
 * anonymous without returning any authentication error.
 *
 * USER_ROLE is a constant, so every scenario runs in its own subprocess
 * (same technique as tests/SessionSetTest.php) with a real signed JWT.
 */
final class ModuleOpportunisticAuthTest extends TestCase
{
    /**
     * Run a script in a fresh PHP process and return the JSON object it echoed.
     *
     * @return array<string, mixed>
     */
    private function runScenario(string $script): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'oa_') . '.php';
        file_put_contents($tmp, "<?php\n" . $script);

        $vendor = dirname(CORE_PATH) . '/../vendor';
        $vendor = realpath($vendor) ?: $vendor;
        $cmd = 'OA_VENDOR=' . escapeshellarg($vendor) . ' '
             . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1';
        $output = (string) shell_exec($cmd);
        @unlink($tmp);

        $json = null;
        foreach ($this->extractJsonObjects($output) as $decoded) {
            // Prefer the terminating response (has http_code) over the marker dump.
            if (array_key_exists('http_code', $decoded)) {
                $json = $decoded;
            } elseif (($decoded['marker'] ?? false) && $json === null) {
                $json = $decoded;
            }
        }
        return ['raw' => $output, 'json' => $json];
    }

    /**
     * Extract every top-level JSON object from a possibly concatenated output.
     *
     * @return array<int, array<string, mixed>>
     */
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
        $keys = JWTKEYS_PATH;
        return <<<PHP
define('CORE_PATH', '{$core}');
define('MESSAGE', 'karewamonitorv2');
define('JWTKEYS_PATH', '{$keys}');
define('JWT_ENCODING', 'RS256');
define('SESSION_TIME', 86400);
define('SESSION_BLACKLIST_RETENTION', 0);
define('IDENTIFIER_UID', 'test-uid');
define('ERROR_LOG_FILE', '/tmp/oa_error.log');
define('DEBUG_LOG_FILE', '/tmp/oa_debug.log');
define('DEBUG_LOG_PATH', '/tmp');
define('MODULE', 'config');
define('REQUEST_TYPE', 'index');
// error_logs() crea el archivo si falta, y su rama de creacion revienta con un
// recurso cerrado; tocarlo antes evita depender del orden de las pruebas.
if (!file_exists(DEBUG_LOG_FILE)) { @touch(DEBUG_LOG_FILE); }
error_reporting(E_ERROR);
require_once getenv('OA_VENDOR') . '/autoload.php';
require_once '{$core}helpers/log.manager.php';
require_once '{$core}helpers/custom_exceptions.php';
require_once '{$core}model/conexion.php';
require_once '{$core}auth/jwt_token.php';
require_once '{$core}helpers/session_manager.php';
require_once '{$core}helpers/api_response.php';
require_once '{$core}auth/session.set.php';
require_once '{$core}auth/module.access.controller.php';

// CLI no trae apache_request_headers: el header Authorization se inyecta aqui.
function apache_request_headers() {
    \$token = getenv('OA_TOKEN');
    return (!\$token) ? [] : ['Authorization' => \$token];
}

\$pdo = new PDO('sqlite::memory:');
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
\$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
\$pdo->exec('CREATE TABLE user_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, jti TEXT NOT NULL, issued_at INTEGER NOT NULL, expires_at INTEGER NOT NULL, UNIQUE (user_id))');
\$pdo->exec('CREATE TABLE token_blacklist (id INTEGER PRIMARY KEY AUTOINCREMENT, jti TEXT NOT NULL, user_id INTEGER NOT NULL, expires_at INTEGER NOT NULL, reason TEXT NOT NULL DEFAULT "revoked", revoked_at INTEGER NOT NULL, UNIQUE (jti))');
App\\Model\\DB::setConnection(\$pdo);
App\\Model\\DB::setPrefix('');

// Minta un token y lo deja como sesion activa, sin definir USER_ROLE (lo hace
// SessionSet::Login, que ensuciaria la constante de la prueba).
function oa_mint(array \$user): string {
    \$jwt = App\\Auth\\jwtToken::encode(\$user, false);
    \\SessionManager::replace((int) \$user['id'], \$jwt->jti, time(), (int) \$jwt->expiration);
    return \$jwt->token;
}

class PublicConfigProbe {
    public function index(): void {
        echo json_encode([
            'marker'         => true,
            'authenticated'  => defined('AUTHENTICATED') ? AUTHENTICATED : null,
            'role'           => defined('USER_ROLE') ? USER_ROLE : null,
        ]) . "\\n";
    }
}
PHP;
    }

    public function testValidTokenExposesTheCallerRoleOnAPublicMethod(): void
    {
        $script = $this->harness() . <<<'PHP'

$token = oa_mint(['id' => 7, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@domain.com', 'role_id' => 2]);
putenv('OA_TOKEN=' . $token);

App\Auth\ModuleHandler::Validate(['index' => [false, NULL]], new PublicConfigProbe());
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertArrayNotHasKey('http_code', $result['json'], 'A public method must not answer an auth error');
        $this->assertTrue($result['json']['authenticated']);
        $this->assertSame(2, $result['json']['role']);
    }

    public function testPublicMethodRunsAnonymousWithoutAToken(): void
    {
        $script = $this->harness() . <<<'PHP'

putenv('OA_TOKEN');

App\Auth\ModuleHandler::Validate(['index' => [false, NULL]], new PublicConfigProbe());
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertArrayNotHasKey('http_code', $result['json']);
        $this->assertFalse($result['json']['authenticated']);
        $this->assertNull($result['json']['role']);
    }

    public function testCorruptTokenIsIgnoredWithoutAnAuthenticationError(): void
    {
        $script = $this->harness() . <<<'PHP'

putenv('OA_TOKEN=Bearer not.a.valid.jwt');

App\Auth\ModuleHandler::Validate(['index' => [false, NULL]], new PublicConfigProbe());
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertArrayNotHasKey(
            'http_code',
            $result['json'],
            'A rejected token on a public method must not terminate the request. Raw: ' . $result['raw']
        );
        $this->assertFalse($result['json']['authenticated']);
        $this->assertNull($result['json']['role']);
    }
}
