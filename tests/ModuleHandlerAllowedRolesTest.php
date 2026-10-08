<?php

use PHPUnit\Framework\TestCase;

/**
 * Guards the allowed_roles disclosure of the response path through
 * ModuleHandler::Validate(): the module's store/update/destroy role
 * declarations are published as {create, edit, delete}, the field is carried
 * by every response served for an authenticated request (successes and
 * in-method errors alike), and anonymous or rejected authentications never
 * see it.
 *
 * Constants such as AUTHENTICATED and USER_ROLE are process-wide, so every
 * scenario runs in its own subprocess with a real signed JWT (same technique
 * as tests/ModuleOpportunisticAuthTest.php).
 */
final class ModuleHandlerAllowedRolesTest extends TestCase
{
    /**
     * Run a script in a fresh PHP process and return the JSON object it echoed.
     *
     * @return array{raw: string, json: ?array<string, mixed>}
     */
    private function runScenario(string $script): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'arl_') . '.php';
        file_put_contents($tmp, "<?php\n" . $script);

        $vendor = dirname(CORE_PATH) . '/../vendor';
        $vendor = realpath($vendor) ?: $vendor;
        $cmd = 'ARL_VENDOR=' . escapeshellarg($vendor) . ' '
             . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1';
        $output = (string) shell_exec($cmd);
        @unlink($tmp);

        $json = null;
        foreach ($this->extractJsonObjects($output) as $decoded) {
            if (array_key_exists('http_code', $decoded)) {
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
define('ERROR_LOG_FILE', '/tmp/arl_error.log');
define('DEBUG_LOG_FILE', '/tmp/arl_debug.log');
define('DEBUG_LOG_PATH', '/tmp');
define('MODULE', 'config');
define('REQUEST_TYPE', 'index');
if (!file_exists(DEBUG_LOG_FILE)) { @touch(DEBUG_LOG_FILE); }
error_reporting(E_ERROR);
require_once getenv('ARL_VENDOR') . '/autoload.php';
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
    \$token = getenv('ARL_TOKEN');
    return (!\$token) ? [] : ['Authorization' => \$token];
}

\$pdo = new PDO('sqlite::memory:');
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
\$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
\$pdo->exec('CREATE TABLE user_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, jti TEXT NOT NULL, issued_at INTEGER NOT NULL, expires_at INTEGER NOT NULL, UNIQUE (user_id))');
\$pdo->exec('CREATE TABLE token_blacklist (id INTEGER PRIMARY KEY AUTOINCREMENT, jti TEXT NOT NULL, user_id INTEGER NOT NULL, issued_at INTEGER NOT NULL, expires_at INTEGER NOT NULL, reason TEXT NOT NULL DEFAULT "revoked", revoked_at INTEGER NOT NULL, UNIQUE (jti))');
App\\Model\\DB::setConnection(\$pdo);
App\\Model\\DB::setPrefix('');

function arl_mint(array \$user): string {
    \$jwt = App\\Auth\\jwtToken::encode(\$user, false);
    \\SessionManager::replace((int) \$user['id'], \$jwt->jti, time(), (int) \$jwt->expiration);
    return \$jwt->token;
}

class AllowedRolesSuccessProbe {
    public function index(): void {
        \\App\\Helpers\\ApiResponse::Set('SUCCESS');
    }
}

class AllowedRolesValidationFailProbe {
    public function index(): void {
        \\App\\Helpers\\ApiResponse::Set('400000');
    }
}
PHP;
    }

    public function testAuthenticatedResponseReportsAllThreeActions(): void
    {
        $script = $this->harness() . <<<'PHP'

$token = arl_mint(['id' => 7, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@domain.com', 'role_id' => 5]);
putenv('ARL_TOKEN=' . $token);

$accepted = [
    'index'   => [true, [5]],
    'store'   => [true, [1, 2, 3]],
    'update'  => [true, [1]],
    'destroy' => [true, [1, 2]],
];
App\Auth\ModuleHandler::Validate($accepted, new AllowedRolesSuccessProbe());
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertArrayHasKey('allowed_roles', $result['json'], 'Raw: ' . $result['raw']);
        $this->assertSame(
            ['create' => [1, 2, 3], 'edit' => [1], 'delete' => [1, 2]],
            $result['json']['allowed_roles']
        );
        $this->assertSame('SUCCESS', $result['json']['code']);
        $this->assertSame('test-uid', $result['json']['meta']['session_id']);
    }

    public function testAuthenticatedResponseReportsOnlyDeclaredActionsAndEmptyRoleList(): void
    {
        $script = $this->harness() . <<<'PHP'

$token = arl_mint(['id' => 7, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@domain.com', 'role_id' => 1]);
putenv('ARL_TOKEN=' . $token);

$accepted = [
    'index' => [true, [1]],
    'store' => [true],
];
App\Auth\ModuleHandler::Validate($accepted, new AllowedRolesSuccessProbe());
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertArrayHasKey('allowed_roles', $result['json'], 'Raw: ' . $result['raw']);
        $this->assertSame(['create' => []], $result['json']['allowed_roles']);
    }

    public function testAuthenticatedResponseWithNoStandardActionsCarriesEmptyObject(): void
    {
        $script = $this->harness() . <<<'PHP'

$token = arl_mint(['id' => 7, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@domain.com', 'role_id' => 1]);
putenv('ARL_TOKEN=' . $token);

App\Auth\ModuleHandler::Validate(['index' => [true, [1]]], new AllowedRolesSuccessProbe());
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertArrayHasKey('allowed_roles', $result['json'], 'Raw: ' . $result['raw']);
        $this->assertSame([], $result['json']['allowed_roles']);
        $this->assertStringContainsString('"allowed_roles":{}', $result['raw']);
    }

    public function testAuthenticatedErrorStillCarriesAllowedRoles(): void
    {
        $script = $this->harness() . <<<'PHP'

$token = arl_mint(['id' => 7, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@domain.com', 'role_id' => 1]);
putenv('ARL_TOKEN=' . $token);

$accepted = [
    'index'   => [true, [1]],
    'store'   => [true, [1, 2]],
    'update'  => [true, [1]],
    'destroy' => [true, [1]],
];
App\Auth\ModuleHandler::Validate($accepted, new AllowedRolesValidationFailProbe());
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertSame('APP_PAYLOAD_VALIDATION', $result['json']['code']);
        $this->assertSame(400, $result['json']['http_code']);
        $this->assertSame(
            ['create' => [1, 2], 'edit' => [1], 'delete' => [1]],
            $result['json']['allowed_roles']
        );
    }

    public function testPublicMethodWithValidTokenCarriesAllowedRoles(): void
    {
        $script = $this->harness() . <<<'PHP'

$token = arl_mint(['id' => 7, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@domain.com', 'role_id' => 2]);
putenv('ARL_TOKEN=' . $token);

$accepted = [
    'index' => [false],
    'store' => [true, [1, 2]],
];
App\Auth\ModuleHandler::Validate($accepted, new AllowedRolesSuccessProbe());
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertSame('SUCCESS', $result['json']['code']);
        $this->assertSame(['create' => [1, 2]], $result['json']['allowed_roles']);
    }

    public function testPublicMethodWithoutTokenOmitsAllowedRoles(): void
    {
        $script = $this->harness() . <<<'PHP'

putenv('ARL_TOKEN');

$accepted = [
    'index' => [false],
    'store' => [true, [1, 2]],
];
App\Auth\ModuleHandler::Validate($accepted, new AllowedRolesSuccessProbe());
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertSame('SUCCESS', $result['json']['code']);
        $this->assertArrayNotHasKey('allowed_roles', $result['json']);
        $this->assertStringNotContainsString('allowed_roles', $result['raw']);
    }

    public function testRejectedAuthenticationOmitsAllowedRoles(): void
    {
        $script = $this->harness() . <<<'PHP'

putenv('ARL_TOKEN');

$accepted = [
    'index'   => [true, [1]],
    'store'   => [true, [1, 2]],
    'update'  => [true, [1]],
    'destroy' => [true, [1]],
];
App\Auth\ModuleHandler::Validate($accepted, new AllowedRolesSuccessProbe());
PHP;

        $result = $this->runScenario($script);
        $this->assertIsArray($result['json'], 'Raw: ' . $result['raw']);
        $this->assertSame('APP_AUTH_FAILED', $result['json']['code']);
        $this->assertSame(401, $result['json']['http_code']);
        $this->assertArrayNotHasKey('allowed_roles', $result['json']);
        $this->assertStringNotContainsString('allowed_roles', $result['raw']);
    }
}
