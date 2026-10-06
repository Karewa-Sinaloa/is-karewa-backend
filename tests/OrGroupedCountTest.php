<?php

if (!defined('DEBUG_LOG_PATH')) {
    define('DEBUG_LOG_PATH', sys_get_temp_dir() . '/karewa_orm_logs');
}
if (!defined('DEBUG_LOG_FILE')) {
    define('DEBUG_LOG_FILE', DEBUG_LOG_PATH . '/or_grouped_count.log');
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

require_once __DIR__ . '/Support/OrTestCase.php';

use App\Model\DBGet;

/**
 * Guards the count contract: a grouped count returns the number of distinct
 * groups (matching the grouped list), and an ungrouped count returns the total
 * number of matching rows.
 */
final class OrGroupedCountTest extends OrTestCase
{
    public function testGroupedCountEqualsTheNumberOfDistinctGroups(): void
    {
        $this->seedItems(); // categories: one, one, two

        $count = DBGet::Get([
            'table'    => 'items',
            'filters'  => [],
            'joins'    => [],
            'group_by' => ['category'],
        ], 'count');

        $groupedList = DBGet::Get([
            'table'    => 'items',
            'fields'   => ['category'],
            'filters'  => [],
            'joins'    => [],
            'group_by' => ['category'],
        ], 'list');

        $this->assertSame(2, (int) $count['results'], 'The count must be the number of distinct groups');
        $this->assertCount(2, $groupedList, 'The count must match the grouped list row count');
    }

    public function testGroupedCountIsNotTheFirstGroupsRowCount(): void
    {
        // Three rows in 'one' and one in 'two': the pre-fix behavior returned
        // the first group's row count (3), not the number of groups (2).
        $this->pdo->exec("INSERT INTO items (name, category, qty) VALUES ('alpha', 'one', 1)");
        $this->pdo->exec("INSERT INTO items (name, category, qty) VALUES ('beta', 'one', 2)");
        $this->pdo->exec("INSERT INTO items (name, category, qty) VALUES ('delta', 'one', 4)");
        $this->pdo->exec("INSERT INTO items (name, category, qty) VALUES ('gamma', 'two', 3)");

        $count = DBGet::Get([
            'table'    => 'items',
            'filters'  => [],
            'joins'    => [],
            'group_by' => ['category'],
        ], 'count');

        $this->assertSame(2, (int) $count['results'], 'The count must be the number of groups, not a group row count');
        $this->assertNotSame(3, (int) $count['results'], 'The first group has 3 rows; that must not be returned');
    }

    public function testUngroupedCountReturnsTotalRows(): void
    {
        $this->seedItems();

        $count = DBGet::Get([
            'table'   => 'items',
            'filters' => [],
            'joins'   => [],
        ], 'count');

        $this->assertSame(3, (int) $count['results'], 'An ungrouped count must be the total row count');
    }

    public function testGroupedCountWithFilterCountsOnlyMatchingGroups(): void
    {
        $this->seedItems(); // categories one (qty 1,2) and two (qty 3)

        $count = DBGet::Get([
            'table'    => 'items',
            'filters'  => [['qty', '2', '>']],
            'joins'    => [],
            'group_by' => ['category'],
        ], 'count');

        $this->assertSame(1, (int) $count['results'], 'The filtered-out category must not be counted');
    }
}
