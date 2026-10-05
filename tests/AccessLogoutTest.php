<?php

use PHPUnit\Framework\TestCase;

/**
 * Covers the logout revocation path: presenting a valid token must cancel the
 * active session and blacklist its jti, so the token is rejected afterwards.
 * Logout terminates through ApiResponse::Set(), so scenarios run in a
 * subprocess (same technique as SessionSetTest) and inspect the persisted state
 * via a shutdown dump.
 */
final class AccessLogoutTest extends TestCase
{
    private function runScenario(string $script): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'lo_') . '.php';
        file_put_contents($tmp, "<?php\n" . $script);

        $vendor = dirname(CORE_PATH) . '/../vendor';
        $vendor = realpath($vendor) ?: $vendor;
        $cmd = 'LO_VENDOR=' . escapeshellarg($vendor) . ' '
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

    private function stateLine(string $raw): ?array
    {
        $found = null;
        foreach ($this->extractJsonObjects($raw) as $decoded) {
            if (array_key_exists('sessions_after', $decoded)) {
                $found = $decoded;
            }
        }
        return $found;
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
define('MODULE', 'access');
define('ERROR_LOG_FILE', '/tmp/lo_error.log');
define('DEBUG_LOG_FILE', '/tmp/lo_debug.log');
define('DEBUG_LOG_PATH', '/tmp');
require_once getenv('LO_VENDOR') . '/autoload.php';
require_once '{$core}helpers/log.manager.php';
require_once '{$core}helpers/custom_exceptions.php';
require_once '{$core}model/conexion.php';
require_once '{$core}auth/jwt_token.php';
require_once '{$core}helpers/session_manager.php';
require_once '{$core}helpers/api_response.php';
require_once '{$core}auth/session.set.php';

\$pdo = new PDO('sqlite::memory:');
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
\$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
\$pdo->exec('CREATE TABLE user_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, jti TEXT NOT NULL, issued_at INTEGER NOT NULL, expires_at INTEGER NOT NULL, UNIQUE (user_id))');
\$pdo->exec('CREATE TABLE token_blacklist (id INTEGER PRIMARY KEY AUTOINCREMENT, jti TEXT NOT NULL, user_id INTEGER NOT NULL, expires_at INTEGER NOT NULL, reason TEXT NOT NULL DEFAULT "revoked", revoked_at INTEGER NOT NULL, UNIQUE (jti))');
App\\Model\\DB::setConnection(\$pdo);
App\\Model\\DB::setPrefix('');

function lo_user(\$id = 1) {
    return ['id' => \$id, 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'user@domain.com', 'role_id' => 1];
}
function lo_sessions(\$pdo) { return \$pdo->query('SELECT * FROM user_sessions')->fetchAll(); }
function lo_blacklist(\$pdo) { return \$pdo->query('SELECT * FROM token_blacklist')->fetchAll(); }
PHP;
    }

    /**
     * Loads the framework pieces AppAccess needs (BaseModel/DBGet/etc.) without
     * the access controller itself, so index.php can require it exactly once.
     */
    private function accessBootstrap(): string
    {
        $core = CORE_PATH;
        return <<<PHP
require_once '{$core}bootstrap/midelware.php';
require_once getenv('LO_VENDOR') . '/autoload.php';
PHP;
    }

    private function controllerRequire(): string
    {
        $core = CORE_PATH;
        return "require_once '{$core}modules/access/local_login.php';\n";
    }

    public function testLogoutCancelsSessionAndBlacklistsToken(): void
    {
        $script = $this->harness()
            . "\$_SERVER['HTTP_AUTHORIZATION'] = '';\n"
            . $this->accessBootstrap()
            . $this->controllerRequire() . <<<'PHP'

$login = App\Auth\SessionSet::Login(lo_user(1), false);
$decoded = App\Auth\jwtToken::decode($login['access_token']);
$jti = $decoded->token_data->jti;

$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $login['access_token'];

register_shutdown_function(function () use ($pdo, $jti) {
    $sessions = lo_sessions($pdo);
    $bl = lo_blacklist($pdo);
    fwrite(STDOUT, json_encode([
        'sessions_after' => count($sessions),
        'blacklist_jtis' => array_column($bl, 'jti'),
        'presented_jti'  => $jti,
    ]) . "\n");
});

$access = new AppAccess();
$access->Logout();
echo json_encode(['marker' => true, 'unreachable' => true]);
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertSame(200, $result['json']['http_code']);
        $this->assertSame('SUCCESS', $result['json']['code']);

        $state = $this->stateLine($result['raw']);
        $this->assertIsArray($state, 'Expected a state dump. Raw: ' . $result['raw']);
        $this->assertSame(0, $state['sessions_after'], 'Logout cancels the active session');
        $this->assertContains($state['presented_jti'], $state['blacklist_jtis'], 'Logout blacklists the token jti');
    }

    public function testLogoutWithoutTokenStillSucceeds(): void
    {
        $script = $this->harness()
            . "\$_SERVER['HTTP_AUTHORIZATION'] = '';\n"
            . $this->accessBootstrap()
            . $this->controllerRequire() . <<<'PHP'

$access = new AppAccess();
$access->Logout();
echo json_encode(['marker' => true, 'unreachable' => true]);
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertSame(200, $result['json']['http_code']);
        $this->assertSame('SUCCESS', $result['json']['code']);
    }

    public function testTokenIsRejectedAfterLogout(): void
    {
        $script = $this->harness()
            . "\$_SERVER['HTTP_AUTHORIZATION'] = '';\n"
            . $this->accessBootstrap() . <<<'PHP'

$login = App\Auth\SessionSet::Login(lo_user(1), false);
$token = $login['access_token'];
$decoded = App\Auth\jwtToken::decode($token);
$jti = $decoded->token_data->jti;
$exp = $decoded->token_data->exp;

// Logout side effects (as Logout() performs them).
\SessionManager::cancel(1);
\SessionManager::blacklist($jti, 1, $exp, 'logout');

// The token is no longer usable: validation must reject it with 901008.
App\Auth\SessionSet::Validate($token);
echo json_encode(['marker' => true, 'unreachable' => true]);
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertSame(401, $result['json']['http_code']);
        $this->assertSame('APP_AUTH_SESSION_REVOKED', $result['json']['code']);
        $this->assertArrayNotHasKey('unreachable', $result['json']);
    }

    public function testLogoutRouteSucceedsWhenAuthenticated(): void
    {
        $script = $this->harness()
            . $this->accessBootstrap() . <<<'PHP'

define('REQUEST_TYPE', 'index');
$_GET['s'] = 'logout';

$login = App\Auth\SessionSet::Login(lo_user(1), false);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $login['access_token'];

chdir(CORE_PATH . 'modules/access');
require CORE_PATH . 'modules/access/index.php';
echo json_encode(['marker' => true, 'unreachable' => true]);
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertSame(200, $result['json']['http_code']);
        $this->assertSame('SUCCESS', $result['json']['code']);
    }

    public function testLogoutRouteSucceedsWithoutToken(): void
    {
        $script = $this->harness()
            . "\$_SERVER['HTTP_AUTHORIZATION'] = '';\n"
            . $this->accessBootstrap() . <<<'PHP'

define('REQUEST_TYPE', 'index');
$_GET['s'] = 'logout';

chdir(CORE_PATH . 'modules/access');
require CORE_PATH . 'modules/access/index.php';
echo json_encode(['marker' => true, 'unreachable' => true]);
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertSame(200, $result['json']['http_code']);
        $this->assertSame('SUCCESS', $result['json']['code']);
    }
}
