<?php

if (!defined('DEBUG_LOG_PATH')) {
    define('DEBUG_LOG_PATH', sys_get_temp_dir() . '/karewa_orm_logs');
}
if (!defined('DEBUG_LOG_FILE')) {
    define('DEBUG_LOG_FILE', DEBUG_LOG_PATH . '/or_get.log');
}
if (!function_exists('error_logs')) {
    require_once CORE_PATH . 'helpers/log.manager.php';
}
if (!is_dir(DEBUG_LOG_PATH)) {
    @mkdir(DEBUG_LOG_PATH, 0777, true);
}
if (!file_exists(DEBUG_LOG_FILE)) {
    @touch(DEBUG_LOG_FILE);
}
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

require_once CORE_PATH . 'helpers/custom_exceptions.php';
require_once CORE_PATH . 'model/conexion.php';
require_once CORE_PATH . 'model/get.php';

use PHPUnit\Framework\TestCase;
use App\Model\DB;
use App\Model\DBGet;

/**
 * Captures the SQL passed to the driver so the filter builder can be asserted
 * against a known in-memory schema.
 */
final class OrGetLoggingPdo extends PDO
{
    /** @var array<int, string> */
    public array $statements = [];

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->statements[] = $query;
        return parent::prepare($query, $options);
    }
}

/**
 * Data-layer tests for DBGet fleshed out with an IN-operator case.
 */
final class OrGetTest extends TestCase
{
    private OrGetLoggingPdo $pdo;

    protected function setUp(): void
    {
        $this->pdo = new OrGetLoggingPdo('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec(
            'CREATE TABLE items ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' name TEXT NOT NULL,'
            . ' category TEXT,'
            . ' qty INTEGER DEFAULT 0'
            . ')'
        );
        $this->pdo->exec(
            'CREATE TABLE categories ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' name TEXT NOT NULL'
            . ')'
        );

        DB::setConnection($this->pdo);
        DB::setPrefix('');
    }

    protected function tearDown(): void
    {
        DB::reset();
    }

    private function seed(): void
    {
        $this->pdo->exec("INSERT INTO items (name, category, qty) VALUES ('alpha', 'one', 1)");
        $this->pdo->exec("INSERT INTO items (name, category, qty) VALUES ('beta', 'one', 2)");
        $this->pdo->exec("INSERT INTO items (name, category, qty) VALUES ('gamma', 'two', 3)");
    }

    /** @return array<int, array<string, mixed>> */
    private function list(array $params): array
    {
        return DBGet::Get($params + [
            'table'  => 'items',
            'joins'  => [],
            'page'   => 1,
        ], 'list') ?? [];
    }

    public function testInOperatorProducesOneInClauseAndMatchingRows(): void
    {
        $this->pdo->exec("INSERT INTO items (name, category, qty) VALUES ('alpha', 'one', 1)");
        $this->pdo->exec("INSERT INTO items (name, category, qty) VALUES ('beta', 'two', 2)");
        $this->pdo->exec("INSERT INTO items (name, category, qty) VALUES ('gamma', 'three', 3)");

        $result = DBGet::Get([
            'table'      => 'items',
            'fields'     => ['id', 'name'],
            'filters'    => [['id', '1,3', 'IN']],
            'joins'      => [],
            'page'       => 1,
            'max_results' => 0,
        ], 'list');

        $sql = implode("\n", $this->pdo->statements);

        $this->assertStringContainsString('id IN (:id1_0,:id1_1)', $sql, 'The IN operator must build a single IN clause');
        $this->assertSame(1, substr_count($sql, 'IN ('), 'A single IN branch must handle the operator');

        $names = array_column($result ?? [], 'name');
        sort($names);
        $this->assertSame(['alpha', 'gamma'], $names);
    }

    public function testFieldSelectionLimitsTheReturnedColumns(): void
    {
        $this->seed();

        $rows = $this->list(['fields' => ['id', 'name'], 'filters' => []]);

        $this->assertCount(3, $rows);
        $this->assertSame(['id', 'name'], array_keys($rows[0]), 'Only the selected columns may be returned');
    }

    public function testEqualityFilterBindsAParameterAndReturnsOnlyMatchingRows(): void
    {
        $this->seed();

        $rows = $this->list(['fields' => ['name'], 'filters' => [['category', 'one', '=']]]);

        $sql = end($this->pdo->statements);
        $this->assertStringContainsString('category = :category1', $sql, 'The filter must use a bound parameter');
        $this->assertSame(['alpha', 'beta'], array_column($rows, 'name'));
    }

    public function testRangeAndInequalityOperatorsFilterCorrectly(): void
    {
        $this->seed();

        $gt = $this->list(['fields' => ['name'], 'filters' => [['qty', '1', '>']]]);
        $this->assertSame(['beta', 'gamma'], array_column($gt, 'name'));

        $lte = $this->list(['fields' => ['name'], 'filters' => [['qty', '2', '<=']]]);
        $this->assertSame(['alpha', 'beta'], array_column($lte, 'name'));

        $ne = $this->list(['fields' => ['name'], 'filters' => [['qty', '1', '!=']]]);
        $this->assertSame(['beta', 'gamma'], array_column($ne, 'name'));
    }

    public function testLikeOperatorMatchesASubstring(): void
    {
        $this->seed();

        $rows = $this->list(['fields' => ['name'], 'filters' => [['name', 'alph', 'LIKE']]]);

        $this->assertSame(['alpha'], array_column($rows, 'name'));
    }

    public function testNullOperatorsFilterNullCategories(): void
    {
        $this->seed();
        $this->pdo->exec("INSERT INTO items (name, category, qty) VALUES ('delta', NULL, 4)");

        $nulls = $this->list(['fields' => ['name'], 'filters' => [['category', '', 'IS_NULL']]]);
        $this->assertSame(['delta'], array_column($nulls, 'name'));

        $notNulls = $this->list(['fields' => ['name'], 'filters' => [['category', '', 'NOT_NULL']]]);
        $this->assertSame(['alpha', 'beta', 'gamma'], array_column($notNulls, 'name'));
    }

    public function testJoinBringsInColumnsFromTheJoinedTable(): void
    {
        $this->seed();
        $this->pdo->exec("INSERT INTO categories (name) VALUES ('one')");
        $this->pdo->exec("INSERT INTO categories (name) VALUES ('two')");

        $rows = $this->list([
            'fields' => ['items.name', 'categories.id AS category_id'],
            'filters' => [],
            'joins' => [
                ['table' => 'categories', 'match' => ['items.category', 'categories.name', 'LEFT']],
            ],
        ]);

        $byName = array_column($rows, 'category_id', 'name');
        $this->assertSame(1, (int) $byName['alpha'], "items.category 'one' must match categories.id 1");
        $this->assertSame(2, (int) $byName['gamma'], "items.category 'two' must match categories.id 2");
        $this->assertStringContainsString('LEFT JOIN categories ON items.category=categories.name', end($this->pdo->statements));
    }

    public function testOrderingSortsRowsAscendingAndDescending(): void
    {
        $this->seed();

        $desc = $this->list(['fields' => ['name'], 'filters' => [], 'order' => [['qty', 'DESC']]]);
        $this->assertSame(['gamma', 'beta', 'alpha'], array_column($desc, 'name'));

        $asc = $this->list(['fields' => ['name'], 'filters' => [], 'order' => [['name', 'ASC']]]);
        $this->assertSame(['alpha', 'beta', 'gamma'], array_column($asc, 'name'));
    }

    public function testSingleRecordActionReturnsOneRowWhileListReturnsAll(): void
    {
        $this->seed();

        $single = DBGet::Get([
            'table'   => 'items',
            'fields'  => ['name'],
            'filters' => [],
            'joins'   => [],
        ]);
        $this->assertSame('alpha', $single['name'], 'The default action must fetch one record');
        $this->assertStringContainsString('LIMIT 1', end($this->pdo->statements));

        $all = $this->list(['fields' => ['name'], 'filters' => []]);
        $this->assertCount(3, $all);
        $this->assertArrayHasKey('name', $all[0]);
    }

    public function testEmptyResultReturnsNull(): void
    {
        $this->seed();

        $rows = DBGet::Get([
            'table'   => 'items',
            'fields'  => ['name'],
            'filters' => [['name', 'missing', '=']],
            'joins'   => [],
            'page'    => 1,
        ], 'list');

        $this->assertNull($rows, 'A query without rows must return NULL');
    }
}
