<?php

use PHPUnit\Framework\TestCase;

/**
 * Guards the per-row edit permissions of the config module: a role listed in
 * a row's edit_roles may update and delete it, a role outside the list is
 * rejected with 901009 (APP_AUTH_ROW_FORBIDDEN) leaving the row untouched,
 * role 1 keeps edit access even when it is absent from the list, store skips
 * the check and defaults the new row to 1,2,3, and a payload carrying
 * edit_roles cannot change the stored list.
 *
 * USER_ROLE and REQUEST_TYPE are constants, so every scenario runs in its own
 * subprocess (the pattern of tests/SessionSetTest.php) over SQLite.
 */
final class ConfigEditRolesTest extends TestCase
{
    /**
     * Run a script in a fresh PHP process and return its terminating response
     * plus the row state dumped on shutdown.
     *
     * @return array{response: ?array<string, mixed>, state: ?array<string, mixed>, raw: string}
     */
    private function runScenario(string $script): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cer_') . '.php';
        file_put_contents($tmp, "<?php\n" . $script);

        $vendor = dirname(CORE_PATH) . '/../vendor';
        $vendor = realpath($vendor) ?: $vendor;
        $cmd = 'CER_VENDOR=' . escapeshellarg($vendor) . ' '
             . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1';
        $output = (string) shell_exec($cmd);
        @unlink($tmp);

        $response = null;
        $state = null;
        foreach ($this->extractJsonObjects($output) as $decoded) {
            if (array_key_exists('http_code', $decoded)) {
                $response = $decoded;
            } elseif (($decoded['marker'] ?? false) && $state === null) {
                $state = $decoded;
            }
        }
        return ['response' => $response, 'state' => $state, 'raw' => $output];
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
     * Run one write against a seeded database and report the response plus the
     * rows as they ended up.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $get
     * @return array{response: ?array<string, mixed>, state: ?array<string, mixed>, raw: string}
     */
    private function write(string $method, int $role, array $payload, array $get): array
    {
        $core      = CORE_PATH;
        $methodPhp = var_export($method, true);
        $payloadPhp = 'json_decode(' . var_export(json_encode($payload), true) . ')';
        $getPhp    = var_export($get, true);

        $script = <<<PHP
define('CORE_PATH', '{$core}');
define('MODULE', 'config');
define('REQUEST_TYPE', '{$method}');
define('IDENTIFIER_UID', 'test-uid');
define('ERROR_LOG_FILE', '/tmp/cer_error.log');
define('DEBUG_LOG_FILE', '/tmp/cer_debug.log');
define('DEBUG_LOG_PATH', '/tmp');
if (!file_exists(DEBUG_LOG_FILE)) { @touch(DEBUG_LOG_FILE); }
error_reporting(E_ERROR);
require_once getenv('CER_VENDOR') . '/autoload.php';
require_once '{$core}helpers/log.manager.php';
require_once '{$core}helpers/custom_exceptions.php';
require_once '{$core}model/conexion.php';
require_once '{$core}model/get.php';
require_once '{$core}model/store.php';
require_once '{$core}model/update.php';
require_once '{$core}model/delete.php';
require_once '{$core}validation/fields.php';
require_once '{$core}helpers/api_response.php';
require_once '{$core}bootstrap/midelware.php';
require_once '{$core}modules/config/controller.php';

\$pdo = new PDO('sqlite::memory:');
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
\$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
\$pdo->exec('CREATE TABLE config (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT NOT NULL, value TEXT, is_private INTEGER NOT NULL DEFAULT 1, edit_roles TEXT NOT NULL DEFAULT "1,2,3")');
\$pdo->exec("INSERT INTO config (id, name, slug, value, is_private, edit_roles) VALUES
    (1, 'Alpha', 'alpha', '{}', 0, '1,2,3'),
    (2, 'Beta', 'beta', '{}', 0, '1,2'),
    (3, 'Gamma', 'gamma', '{}', 0, '2')");
App\\Model\\DB::setConnection(\$pdo);
App\\Model\\DB::setPrefix('');

register_shutdown_function(function () use (\$pdo) {
    \$rows = \$pdo->query('SELECT id, name, edit_roles FROM config ORDER BY id')->fetchAll();
    echo json_encode(['marker' => true, 'row_states' => \$rows]) . "\\n";
});

\$_GET = array_merge(['page' => '1', 'limit' => '10', 'fields' => '', 'search' => '', 'sort' => '', 'groupby' => '', 'id' => ''], {$getPhp});
\$_payload = {$payloadPhp};
define('USER_ROLE', {$role});

\$appConfig = new AppConfig();
\$appConfig->{$method}();
PHP;

        return $this->runScenario($script);
    }

    /** @return array<string, mixed> */
    private function row(array $result, int $id): array
    {
        foreach ($result['state']['row_states'] ?? [] as $row) {
            if ((int) $row['id'] === $id) {
                return $row;
            }
        }
        $this->fail('Row ' . $id . ' not found. Raw: ' . $result['raw']);
    }

    public function testRoleInTheListCanUpdateTheRow(): void
    {
        $result = $this->write('update', 2, ['name' => 'Alpha updated'], ['id' => '1']);

        $this->assertIsArray($result['response'], 'Raw: ' . $result['raw']);
        $this->assertSame(200, $result['response']['http_code'], 'Raw: ' . $result['raw']);
        $this->assertSame('Alpha updated', $this->row($result, 1)['name']);
    }

    public function testRoleOutsideTheListIsRejectedAndTheRowStaysIntact(): void
    {
        $result = $this->write('update', 3, ['name' => 'Beta hacked'], ['id' => '2']);

        $this->assertIsArray($result['response'], 'Raw: ' . $result['raw']);
        $this->assertSame(403, $result['response']['http_code'], 'Raw: ' . $result['raw']);
        $this->assertSame('APP_AUTH_ROW_FORBIDDEN', $result['response']['code'] ?? null);
        $this->assertSame('Beta', $this->row($result, 2)['name'], 'The row must not be modified');
    }

    public function testRoleOutsideTheListCannotDeleteTheRow(): void
    {
        $result = $this->write('destroy', 3, [], ['id' => '2']);

        $this->assertIsArray($result['response'], 'Raw: ' . $result['raw']);
        $this->assertSame(403, $result['response']['http_code'], 'Raw: ' . $result['raw']);
        $this->assertSame('APP_AUTH_ROW_FORBIDDEN', $result['response']['code'] ?? null);
        $this->assertCount(3, $result['state']['row_states'] ?? [], 'No row may be deleted. Raw: ' . $result['raw']);
    }

    public function testAdministratorKeepsEditAccessEvenOutsideTheList(): void
    {
        $result = $this->write('update', 1, ['name' => 'Gamma updated'], ['id' => '3']);

        $this->assertIsArray($result['response'], 'Raw: ' . $result['raw']);
        $this->assertSame(200, $result['response']['http_code'], 'Raw: ' . $result['raw']);
        $this->assertSame('Gamma updated', $this->row($result, 3)['name']);
    }

    public function testStoreSkipsTheCheckAndDefaultsToTheFullList(): void
    {
        // Role 3 is outside the list of row 2, and an id is present on purpose:
        // creation must not be gated by the row permissions of some other row.
        $result = $this->write('store', 3, ['name' => 'Nueva', 'slug' => 'nueva'], ['id' => '2']);

        $this->assertIsArray($result['response'], 'Raw: ' . $result['raw']);
        $this->assertSame(201, $result['response']['http_code'], 'Raw: ' . $result['raw']);
        $this->assertSame('1,2,3', $this->row($result, 4)['edit_roles'], 'A new row must start editable by 1, 2 and 3');
    }

    public function testEditRolesInPayloadCannotChangeTheStoredList(): void
    {
        $result = $this->write(
            'store',
            3,
            ['name' => 'Otra', 'slug' => 'otra', 'edit_roles' => '3'],
            ['id' => '2']
        );

        $this->assertIsArray($result['response'], 'Raw: ' . $result['raw']);
        $this->assertSame(201, $result['response']['http_code'], 'Raw: ' . $result['raw']);
        $this->assertSame('1,2,3', $this->row($result, 4)['edit_roles'], 'The API must not accept a new list');
    }
}
