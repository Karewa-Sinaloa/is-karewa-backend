<?php

use PHPUnit\Framework\TestCase;

/**
 * Guards the visibility filter declared by the config module: callers without
 * a role in [1, 2, 3] only see rows with is_private = 0, the query string
 * cannot lift that filter, and the zero flag survives persistence (creation
 * and update) instead of falling back to the default.
 *
 * USER_ROLE and REQUEST_TYPE are constants, so each scenario runs in its own
 * subprocess, following the pattern of tests/SessionSetTest.php.
 */
final class ConfigVisibilityFilterTest extends TestCase
{
    /**
     * Run a script in a fresh PHP process and return the JSON object it echoed.
     *
     * @return array<string, mixed>
     */
    private function runJson(string $script): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cv_') . '.php';
        file_put_contents($tmp, "<?php\n" . $script);

        $vendor = dirname(CORE_PATH) . '/../vendor';
        $vendor = realpath($vendor) ?: $vendor;
        $cmd = 'CV_VENDOR=' . escapeshellarg($vendor) . ' '
             . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1';
        $output = (string) shell_exec($cmd);
        @unlink($tmp);

        $json = null;
        foreach (explode("\n", $output) as $line) {
            $decoded = json_decode(trim($line), true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }
        $this->assertIsArray($json, 'Raw: ' . $output);
        return $json;
    }

    /**
     * Probe the module's init()/queryFields() with a fixed request type, an
     * optional role and an optional payload, without touching any database.
     *
     * @param array<string, mixed>      $get
     * @param array<string, mixed>|null $payload
     */
    private function harness(string $requestType, ?int $role, array $get = [], ?array $payload = null): string
    {
        $core        = CORE_PATH;
        $get_php     = var_export($get, true);
        $payload_php = $payload === null
            ? 'null'
            : 'json_decode(' . var_export(json_encode($payload), true) . ')';
        $role_php = $role === null ? 'null' : (string) $role;

        return <<<PHP
define('CORE_PATH', '{$core}');
define('REQUEST_TYPE', '{$requestType}');
define('MODULE', 'config');
define('IDENTIFIER_UID', 'test-uid');
error_reporting(E_ERROR);
require_once getenv('CV_VENDOR') . '/autoload.php';
require_once '{$core}bootstrap/midelware.php';
require_once '{$core}modules/config/controller.php';
\$_GET = array_merge(
    ['page' => '1', 'limit' => '10', 'fields' => '', 'search' => '', 'sort' => '', 'groupby' => '', 'id' => ''],
    {$get_php}
);
\$_payload = {$payload_php};
\$role = {$role_php};
if (\$role !== null) { define('USER_ROLE', \$role); }

class ConfigProbe extends AppConfig {
    public function filters(): array { return \$this->init()['filters']; }
    public function fields(): array   { return \$this->queryFields(); }
}

\$probe = new ConfigProbe();
echo json_encode(['filters' => \$probe->filters(), 'fields' => \$probe->fields()]) . "\n";
PHP;
    }

    public function testAnonymousCallerIsFilteredToPublicRows(): void
    {
        $result = $this->runJson($this->harness('index', null));

        $this->assertSame(['is_private', 0, '='], $result['filters']['is_private'] ?? null);
    }

    public function testLowerRoleIsFilteredToPublicRows(): void
    {
        $result = $this->runJson($this->harness('index', 4));

        $this->assertSame(['is_private', 0, '='], $result['filters']['is_private'] ?? null);
    }

    public function testAdministrativeRolesSeeEveryRow(): void
    {
        foreach ([1, 2, 3] as $role) {
            $result = $this->runJson($this->harness('index', $role));

            $this->assertArrayNotHasKey(
                'is_private',
                $result['filters'],
                "Role {$role} must not be restricted to public rows"
            );
        }
    }

    public function testQueryStringCannotLiftTheVisibilityFilter(): void
    {
        $result = $this->runJson($this->harness('index', null, ['is_private' => 'eq:1']));

        $this->assertSame(
            ['is_private', 0, '='],
            $result['filters']['is_private'] ?? null,
            'The module filter must overwrite any attempt coming from the query string'
        );
    }

    public function testCreateWithoutTheFlagStoresTheDefault(): void
    {
        $payload = ['name' => 'Entrada', 'slug' => 'entrada'];

        $result = $this->runJson($this->harness('store', 1, [], $payload));

        $this->assertSame(['is_private', 1], $result['fields']['is_private'] ?? null);
    }

    public function testCreateAsPublicStoresZero(): void
    {
        $payload = ['name' => 'Entrada', 'slug' => 'entrada', 'is_private' => 0];

        $result = $this->runJson($this->harness('store', 1, [], $payload));

        $this->assertSame(['is_private', 0], $result['fields']['is_private'] ?? null);
    }

    public function testUpdateToPublicKeepsZero(): void
    {
        $payload = ['name' => 'Entrada', 'is_private' => 0];

        $result = $this->runJson($this->harness('update', 1, [], $payload));

        $this->assertSame(['is_private', 0], $result['fields']['is_private'] ?? null);
    }

    public function testUpdateWithoutTheFlagLeavesItOut(): void
    {
        $payload = ['name' => 'Entrada'];

        $result = $this->runJson($this->harness('update', 1, [], $payload));

        $this->assertArrayNotHasKey('is_private', $result['fields']);
    }
}
