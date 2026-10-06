<?php

if (!defined('DEBUG_LOG_PATH')) {
    define('DEBUG_LOG_PATH', sys_get_temp_dir() . '/karewa_orm_logs');
}
if (!defined('DEBUG_LOG_FILE')) {
    define('DEBUG_LOG_FILE', DEBUG_LOG_PATH . '/or_transaction.log');
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

use App\Model\DB;
use App\Model\DBDelete;
use App\Model\DBStore;
use App\Model\DBUpdate;

/**
 * Guards the transaction contract: a multi-step write that fails partway
 * through leaves no persisted statements, a successful one commits them, and
 * no transaction is left open on any path.
 */
final class OrTransactionTest extends OrTestCase
{
    private function rowCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
    }

    public function testFailureInsideMultiStepWriteRollsBackAllStatements(): void
    {
        DB::begin();
        try {
            DBStore::Store('items', [[['name', 'pending'], ['category', 'one'], ['qty', 1]]]);
            // The second step fails: DBUpdate with no fields raises 902001.
            DBUpdate::Update('items', [], [['id', '1', '=']]);
            $this->fail('The second step of the write must fail');
        } catch (\AppException $e) {
            $this->assertSame(902001, (int) $e->getCode());
            DB::rollback();
        }

        $this->assertFalse($this->pdo->inTransaction(), 'No transaction may stay open after a failure');
        $this->assertSame(0, $this->rowCount(), 'The insert from the failed write must be rolled back');
    }

    public function testSuccessfulMultiStepWriteCommits(): void
    {
        DB::begin();
        DBStore::Store('items', [[['name', 'first'], ['category', 'one'], ['qty', 1]]]);
        DBStore::Store('items', [[['name', 'second'], ['category', 'two'], ['qty', 2]]]);
        DB::commit();

        $this->assertFalse($this->pdo->inTransaction(), 'No transaction may stay open after a commit');
        $this->assertSame(2, $this->rowCount(), 'Both statements of the committed write must persist');
    }

    public function testDeletePathRollsBackOnFailureAndClosesItsTransaction(): void
    {
        $this->seedItems();

        try {
            // The association check passes, then the DELETE statement fails on a
            // non-existent column: the multi-statement path must roll back.
            DBDelete::delete('items', [['no_such_column', '1', '=']], [
                ['table' => 'categories', 'column' => 'name', 'value' => 'missing'],
            ]);
            $this->fail('The delete statement must fail');
        } catch (\AppException $e) {
            $this->assertSame(902003, (int) $e->getCode(), 'The SQL failure must carry the database code');
        }

        $this->assertFalse($this->pdo->inTransaction(), 'The delete path must close its transaction');
        $this->assertSame(3, $this->rowCount(), 'No partial change may persist');
    }

    public function testDeletePathCommitsOnSuccess(): void
    {
        $this->seedItems();

        $deleted = DBDelete::delete('items', [['id', '1', '=']], [
            ['table' => 'categories', 'column' => 'name', 'value' => 'missing'],
        ]);

        $this->assertSame(1, $deleted);
        $this->assertFalse($this->pdo->inTransaction(), 'The delete path must close its transaction');
        $this->assertSame(2, $this->rowCount(), 'The committed delete must persist');
    }

    public function testDeleteInsideAnOuterTransactionLeavesOwnershipToTheCaller(): void
    {
        $this->seedItems();

        DB::begin();
        $deleted = DBDelete::delete('items', [['id', '1', '=']], [
            ['table' => 'categories', 'column' => 'name', 'value' => 'missing'],
        ]);
        DB::commit();

        $this->assertSame(1, $deleted);
        $this->assertFalse($this->pdo->inTransaction());
        $this->assertSame(2, $this->rowCount());
    }
}
