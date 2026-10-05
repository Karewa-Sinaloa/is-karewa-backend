<?php

use PHPUnit\Framework\TestCase;

/**
 * Covers the login/validate lifecycle of SessionSet against an in-memory
 * database with real signed JWTs. The reject path terminates the request through
 * ApiResponse::Set(), so each scenario runs in a subprocess (same technique as
 * RateLimitEnforcementTest) and asserts both the emitted response and the
 * persisted session/blacklist state.
 */
final class SessionSetTest extends TestCase
{
    private function runScenario(string $script): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ss_') . '.php';
        file_put_contents($tmp, "<?php\n" . $script);

        $vendor = dirname(CORE_PATH) . '/../vendor';
        $vendor = realpath($vendor) ?: $vendor;
        $cmd = 'SS_VENDOR=' . escapeshellarg($vendor) . ' '
             . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1';
        $output = (string) shell_exec($cmd);
        @unlink($tmp);

        $json = null;
        foreach ($this->extractJsonObjects($output) as $decoded) {
            // Prefer the terminating response (has http_code); otherwise keep the
            // explicit logic-state dump marked with 'marker'.
            if (array_key_exists('http_code', $decoded)) {
                $json = $decoded;
            } elseif (($decoded['marker'] ?? false) && $json === null) {
                $json = $decoded;
            }
        }
        return ['raw' => $output, 'json' => $json];
    }

    /**
     * Extract every top-level JSON object from a string that may contain several
     * objects concatenated (the terminating response plus a shutdown dump).
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
        $core   = CORE_PATH;
        $keys   = JWTKEYS_PATH;
        return <<<PHP
define('CORE_PATH', '{$core}');
define('MESSAGE', 'karewamonitorv2');
define('JWTKEYS_PATH', '{$keys}');
define('JWT_ENCODING', 'RS256');
define('SESSION_TIME', 86400);
define('SESSION_BLACKLIST_RETENTION', 0);
define('IDENTIFIER_UID', 'test-uid');
define('ERROR_LOG_FILE', '/tmp/ss_error.log');
define('DEBUG_LOG_FILE', '/tmp/ss_debug.log');
define('DEBUG_LOG_PATH', '/tmp');
require_once getenv('SS_VENDOR') . '/autoload.php';
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

function ss_user(\$id = 1) {
    return ['id' => \$id, 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'user@domain.com', 'role_id' => 1];
}
function ss_sessions(\$pdo) { return \$pdo->query('SELECT * FROM user_sessions')->fetchAll(); }
function ss_blacklist(\$pdo) { return \$pdo->query('SELECT * FROM token_blacklist')->fetchAll(); }
PHP;
    }

    public function testActiveTokenIsAccepted(): void
    {
        $script = $this->harness() . <<<'PHP'

$login = App\Auth\SessionSet::Login(ss_user(1), false);
$granted = App\Auth\SessionSet::Validate($login['access_token']);
echo json_encode(['marker' => true, 'granted' => $granted, 'sessions' => count(ss_sessions($pdo))]);
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertTrue($result['json']['granted'], 'The active token must be accepted');
        $this->assertSame(1, $result['json']['sessions'], 'Login stores exactly one active session');
    }

    public function testLoginReplacesPreviousSession(): void
    {
        $script = $this->harness() . <<<'PHP'

$first  = App\Auth\SessionSet::Login(ss_user(1), false);
$second = App\Auth\SessionSet::Login(ss_user(1), false);
echo json_encode([
    'marker' => true,
    'sessions' => count(ss_sessions($pdo)),
    'distinct_tokens' => $first['access_token'] !== $second['access_token'],
]);
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertSame(1, $result['json']['sessions'], 'A second login replaces the first session');
        $this->assertTrue($result['json']['distinct_tokens']);
    }

    public function testSupersededTokenIsRejectedAndBlacklisted(): void
    {
        $script = $this->harness() . <<<'PHP'

$first  = App\Auth\SessionSet::Login(ss_user(1), false);   // becomes active
$second = App\Auth\SessionSet::Login(ss_user(1), false);   // supersedes first

// Dump persisted state on shutdown: Validate() terminates the request through
// ApiResponse::Set(), which triggers registered shutdown functions.
register_shutdown_function(function () use ($pdo, $first, $second) {
    $rows = ss_sessions($pdo);
    $bl   = ss_blacklist($pdo);
    fwrite(STDOUT, json_encode([
        'marker'          => true,
        'sessions_after'  => count($rows),
        'blacklist_jtis'  => array_column($bl, 'jti'),
        'presented_jti'   => App\Auth\jwtToken::decode($first['access_token'])->token_data->jti,
        'active_jti'      => App\Auth\jwtToken::decode($second['access_token'])->token_data->jti,
    ]) . "\n");
});

App\Auth\SessionSet::Validate($first['access_token']);
echo json_encode(['marker' => true, 'unreachable' => true]);
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertSame(401, $result['json']['http_code']);
        $this->assertSame('APP_AUTH_SESSION_REVOKED', $result['json']['code']);
        $this->assertArrayNotHasKey('unreachable', $result['json'], 'Rejection must terminate the request');

        // The state dump (a later JSON line with marker=true) shows the side effects.
        $state = $this->lastStateLine($result['raw']);
        $this->assertIsArray($state, 'Expected a state dump. Raw: ' . $result['raw']);
        $this->assertSame(0, $state['sessions_after'], 'Suspicious use cancels the active session');
        $this->assertContains($state['presented_jti'], $state['blacklist_jtis'], 'The presented token is blacklisted');
        $this->assertNotSame($state['presented_jti'], $state['active_jti']);
    }

    /**
     * Return the last JSON object line in the output that carries the marker and
     * a state key (the shutdown dump emitted after the terminating response).
     */
    private function lastStateLine(string $raw): ?array
    {
        $found = null;
        if (preg_match_all('/\{[^{}]*?"sessions_after"[^{}]*?\}/', $raw, $matches)) {
            foreach ($matches[0] as $candidate) {
                $decoded = json_decode($candidate, true);
                if (is_array($decoded) && array_key_exists('sessions_after', $decoded)) {
                    $found = $decoded;
                }
            }
        }
        return $found;
    }

    public function testBlacklistedActiveTokenIsRejected(): void
    {
        $script = $this->harness() . <<<'PHP'

$login = App\Auth\SessionSet::Login(ss_user(1), false);
// Blacklist the very token that is the active session.
$decoded = App\Auth\jwtToken::decode($login['access_token']);
\SessionManager::blacklist($decoded->token_data->jti, 1, $decoded->token_data->exp, 'logout');

App\Auth\SessionSet::Validate($login['access_token']);
echo json_encode(['marker' => true, 'unreachable' => true]);
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertSame(401, $result['json']['http_code']);
        $this->assertSame('APP_AUTH_SESSION_REVOKED', $result['json']['code']);
        $this->assertArrayNotHasKey('unreachable', $result['json']);
    }

    public function testSignatureOrExpiryErrorsKeepTheirOwnCodes(): void
    {
        // A garbage token fails signature/decoding and must surface the existing
        // JWT error (not the session-revoked code).
        $script = $this->harness() . <<<'PHP'

App\Auth\SessionSet::Validate('Bearer not.a.valid.jwt');
echo json_encode(['marker' => true, 'unreachable' => true]);
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertSame(401, $result['json']['http_code']);
        $this->assertNotSame('APP_AUTH_SESSION_REVOKED', $result['json']['code']);
    }
}
