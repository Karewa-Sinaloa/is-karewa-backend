<?php

use PHPUnit\Framework\TestCase;

/**
 * Guards that DBDelete::is_asociated() queries the associated table with the
 * configured prefix applied exactly once, and that a referenced record is
 * blocked while an unreferenced one deletes normally.
 *
 * The ORM reads the prefix from the MYSQL_PREFIX constant, which phpunit.xml
 * fixes to the empty string, so the double-prefix defect cannot manifest in the
 * PHPUnit process. Each scenario therefore runs in a subprocess that defines a
 * real `dev_` prefix (same technique as SessionSetTest) and reports the issued
 * SQL statements plus the delete outcomes as JSON.
 */
final class DeleteAssociationGuardTest extends TestCase
{
    /** @return array<string, mixed> */
    private function runScenario(): array
    {
        $core = CORE_PATH;
        $tmp  = tempnam(sys_get_temp_dir(), 'dag_') . '.php';
        $log  = sys_get_temp_dir() . '/delete_association_guard_test.log';

        $script = <<<PHP
define('CORE_PATH', '{$core}');
define('MYSQL_PREFIX', 'dev_');
define('DEBUG_LOG_PATH', '{$tmp}_dir');
define('DEBUG_LOG_FILE', '{$log}');
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once '{$core}helpers/log.manager.php';
require_once '{$core}helpers/custom_exceptions.php';
require_once '{$core}model/conexion.php';
require_once '{$core}model/get.php';
require_once '{$core}model/delete.php';

class GuardLoggingPdo extends PDO {
    public array \$statements = [];
    public function prepare(string \$query, array \$options = []): PDOStatement|false {
        \$this->statements[] = \$query;
        return parent::prepare(\$query, \$options);
    }
}

\$pdo = new GuardLoggingPdo('sqlite::memory:');
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
\$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
\$pdo->exec('CREATE TABLE dev_users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT)');
\$pdo->exec('CREATE TABLE dev_customers (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER)');
App\\Model\\DB::setConnection(\$pdo);

\$pdo->exec("INSERT INTO dev_users (id, email) VALUES (1, 'ref@example.com')");
\$pdo->exec("INSERT INTO dev_users (id, email) VALUES (2, 'free@example.com')");
\$pdo->exec('INSERT INTO dev_customers (user_id) VALUES (1)');

\$out = [];
try {
    App\\Model\\DBDelete::delete('users', [['id', 1, '=']], [
        ['table' => 'customers', 'column' => 'user_id', 'value' => 1],
    ]);
    \$out['assoc_exception'] = null;
} catch (AppException \$e) {
    \$out['assoc_exception'] = (int) \$e->getCode();
}
\$out['referenced_rows_after'] = (int) \$pdo->query('SELECT COUNT(*) FROM dev_users WHERE id = 1')->fetchColumn();
\$out['statements'] = \$pdo->statements;

\$out['unreferenced_deleted'] = App\\Model\\DBDelete::delete('users', [['id', 2, '=']], [
    ['table' => 'customers', 'column' => 'user_id', 'value' => 2],
]);
\$out['unreferenced_rows_after'] = (int) \$pdo->query('SELECT COUNT(*) FROM dev_users WHERE id = 2')->fetchColumn();

\$pdo->exec("INSERT INTO dev_users (id, email) VALUES (3, 'noassoc@example.com')");
\$out['noassoc_deleted'] = App\\Model\\DBDelete::delete('users', [['id', 3, '=']], null);
\$out['noassoc_rows_after'] = (int) \$pdo->query('SELECT COUNT(*) FROM dev_users WHERE id = 3')->fetchColumn();

echo 'GUARD_RESULT=' . json_encode(\$out) . "\\n";
PHP;

        file_put_contents($tmp, "<?php\n" . $script);
        $output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1');
        @unlink($tmp);

        foreach (explode("\n", $output) as $line) {
            if (str_starts_with($line, 'GUARD_RESULT=')) {
                $decoded = json_decode(substr($line, strlen('GUARD_RESULT=')), true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        $this->fail('Guard scenario did not report a result. Raw output: ' . $output);
    }

    public function testAssociationLookupUsesConfiguredPrefixExactlyOnce(): void
    {
        $result = $this->runScenario();

        $this->assertSame(
            902002,
            $result['assoc_exception'],
            'The guard must report APP_ENTRY_RELATIONSHIP, not a doubly-prefixed table error'
        );

        $sql = implode("\n", $result['statements']);
        $this->assertStringNotContainsString('dev_dev_', $sql, 'The associated table must not be doubly prefixed');
        $this->assertStringContainsString('dev_customers', $sql, 'The guard must query the prefixed associated table');
    }

    public function testReferencedUserIsBlockedAndUnreferencedUserDeletesNormally(): void
    {
        $result = $this->runScenario();

        $this->assertSame(902002, $result['assoc_exception']);
        $this->assertSame(1, $result['referenced_rows_after'], 'A blocked delete must not remove the row');

        $this->assertSame(1, $result['unreferenced_deleted'], 'An unreferenced user must be deleted');
        $this->assertSame(0, $result['unreferenced_rows_after']);
    }

    public function testModulesWithoutAssociationsSkipTheGuard(): void
    {
        $result = $this->runScenario();

        $this->assertSame(1, $result['noassoc_deleted']);
        $this->assertSame(0, $result['noassoc_rows_after']);
    }
}
