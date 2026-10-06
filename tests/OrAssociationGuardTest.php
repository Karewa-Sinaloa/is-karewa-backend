<?php

if (!defined('DEBUG_LOG_PATH')) {
    define('DEBUG_LOG_PATH', sys_get_temp_dir() . '/karewa_orm_logs');
}
if (!defined('DEBUG_LOG_FILE')) {
    define('DEBUG_LOG_FILE', DEBUG_LOG_PATH . '/or_association_guard.log');
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

use App\Model\DBDelete;

/**
 * Guards the delete association check: when the associated table has a matching
 * row the delete is rejected, and when it does not the delete proceeds.
 */
final class OrAssociationGuardTest extends OrTestCase
{
    public function testGuardRejectsDeleteWhenAssociatedRowsExist(): void
    {
        $this->seedItems();
        $this->pdo->exec("INSERT INTO categories (name) VALUES ('one')");

        try {
            DBDelete::delete('items', [['id', '1', '=']], [
                ['table' => 'categories', 'column' => 'name', 'value' => 'one'],
            ]);
            $this->fail('The association guard must reject a delete while associated rows exist');
        } catch (\AppException $e) {
            $this->assertSame(902002, (int) $e->getCode());
        }

        $remaining = (int) $this->pdo->query('SELECT COUNT(*) FROM items WHERE id = 1')->fetchColumn();
        $this->assertSame(1, $remaining, 'A rejected delete must not remove the row');
    }

    public function testGuardAllowsDeleteWhenNoAssociatedRows(): void
    {
        $this->seedItems();

        $deleted = DBDelete::delete('items', [['id', '3', '=']], [
            ['table' => 'categories', 'column' => 'name', 'value' => 'missing'],
        ]);

        $this->assertSame(1, $deleted);
        $remaining = (int) $this->pdo->query('SELECT COUNT(*) FROM items WHERE id = 3')->fetchColumn();
        $this->assertSame(0, $remaining);
    }
}
