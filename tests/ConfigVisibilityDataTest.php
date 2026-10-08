<?php

use PHPUnit\Framework\TestCase;

/**
 * Guards row visibility against real data: an anonymous listing returns only
 * the entries with is_private = 0 together with a matching pagination count,
 * roles 1, 2 and 3 see every entry, and a private entry answers 404000 to a
 * caller without one of those roles.
 *
 * Every scenario ends on ApiResponse::Set(), which terminates the request, so
 * each one runs in a subprocess (the pattern of tests/SessionSetTest.php) with
 * an in-memory SQLite database.
 */
final class ConfigVisibilityDataTest extends TestCase
{
    /**
     * Run a script in a fresh PHP process and return the terminating response.
     *
     * @return array<string, mixed>
     */
    private function runScenario(string $script): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cvd_') . '.php';
        file_put_contents($tmp, "<?php\n" . $script);

        $vendor = dirname(CORE_PATH) . '/../vendor';
        $vendor = realpath($vendor) ?: $vendor;
        $cmd = 'CVD_VENDOR=' . escapeshellarg($vendor) . ' '
             . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1';
        $output = (string) shell_exec($cmd);
        @unlink($tmp);

        $json = null;
        foreach ($this->extractJsonObjects($output) as $decoded) {
            if (array_key_exists('http_code', $decoded)) {
                $json = $decoded;
            }
        }
        $this->assertIsArray($json, 'Raw: ' . $output);
        return $json;
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

    /**
     * Query the config module against a seeded SQLite database.
     *
     * @param array<string, mixed> $get
     */
    private function query(?int $role, array $get, string $method = 'index'): array
    {
        $core    = CORE_PATH;
        $get_php = var_export($get, true);
        $role_php = $role === null ? 'null' : (string) $role;

        $script = <<<PHP
define('CORE_PATH', '{$core}');
define('MODULE', 'config');
define('REQUEST_TYPE', '{$method}');
define('IDENTIFIER_UID', 'test-uid');
define('ERROR_LOG_FILE', '/tmp/cvd_error.log');
define('DEBUG_LOG_FILE', '/tmp/cvd_debug.log');
define('DEBUG_LOG_PATH', '/tmp');
if (!file_exists(DEBUG_LOG_FILE)) { @touch(DEBUG_LOG_FILE); }
error_reporting(E_ERROR);
require_once getenv('CVD_VENDOR') . '/autoload.php';
require_once '{$core}helpers/log.manager.php';
require_once '{$core}helpers/custom_exceptions.php';
require_once '{$core}model/conexion.php';
require_once '{$core}model/get.php';
require_once '{$core}helpers/api_response.php';
require_once '{$core}bootstrap/midelware.php';
require_once '{$core}modules/config/controller.php';

\$pdo = new PDO('sqlite::memory:');
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
\$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
\$pdo->exec('CREATE TABLE config (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT NOT NULL, value TEXT, is_private INTEGER NOT NULL DEFAULT 1, edit_roles TEXT NOT NULL DEFAULT "1,2,3")');
\$pdo->exec("INSERT INTO config (id, name, slug, value, is_private) VALUES
    (1, 'Publica uno', 'public-one', '{\"k\":1}', 0),
    (2, 'Publica dos', 'public-two', '{\"k\":2}', 0),
    (3, 'Privada uno', 'private-one', '{\"secret\":\"s1\"}', 1),
    (4, 'Privada dos', 'private-two', '{\"secret\":\"s2\"}', 1)");
App\\Model\\DB::setConnection(\$pdo);
App\\Model\\DB::setPrefix('');

\$_GET = array_merge(
    ['page' => '1', 'limit' => '10', 'fields' => '', 'search' => '', 'sort' => '', 'groupby' => '', 'embed' => 'pagination'],
    {$get_php}
);
\$role = {$role_php};
if (\$role !== null) { define('USER_ROLE', \$role); }

\$appConfig = new AppConfig();
\$appConfig->{$method}();
PHP;

        return $this->runScenario($script);
    }

    public function testAnonymousListingReturnsOnlyPublicEntries(): void
    {
        $result = $this->query(null, []);

        $this->assertSame(200, $result['http_code'], 'RAW: ' . json_encode($result));
        $slugs = array_column($result['data'], 'slug');
        sort($slugs);
        $this->assertSame(['public-one', 'public-two'], $slugs, 'Raw: ' . json_encode($result));
        $this->assertSame(2, $result['pagination']['results'], 'The count must match the visible rows');
    }

    public function testAdministrativeRolesSeeEveryEntry(): void
    {
        foreach ([1, 2, 3] as $role) {
            $result = $this->query($role, []);

            $this->assertSame(200, $result['http_code'], 'RAW: ' . json_encode($result));
            $this->assertCount(4, $result['data'], "Role {$role} must see every entry");
            $this->assertSame(4, $result['pagination']['results'], "Role {$role} must count every entry");
        }
    }

    public function testAnonymousItemRequestForAPrivateEntryAnswersNotFound(): void
    {
        $result = $this->query(null, ['id' => '3'], 'show');

        $this->assertSame(404, $result['http_code'], 'RAW: ' . json_encode($result));
        $this->assertSame('APP_RESULTS_NOT_FOUND', $result['code']);
    }

    public function testLowerRoleItemRequestForAPrivateEntryAnswersNotFound(): void
    {
        $result = $this->query(4, ['id' => '4'], 'show');

        $this->assertSame(404, $result['http_code'], 'RAW: ' . json_encode($result));
        $this->assertSame('APP_RESULTS_NOT_FOUND', $result['code']);
    }

    public function testAnonymousItemRequestForAPublicEntrySucceeds(): void
    {
        $result = $this->query(null, ['id' => '1'], 'show');

        $this->assertSame(200, $result['http_code'], 'RAW: ' . json_encode($result));
        $this->assertSame('public-one', $result['data']['slug'] ?? null, 'Raw: ' . json_encode($result));
    }
}
