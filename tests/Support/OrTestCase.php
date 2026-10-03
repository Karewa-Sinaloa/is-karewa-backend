<?php

use PHPUnit\Framework\TestCase;
use App\Model\DB;

require_once CORE_PATH . 'helpers/custom_exceptions.php';
require_once CORE_PATH . 'model/conexion.php';
require_once CORE_PATH . 'model/get.php';
require_once CORE_PATH . 'model/store.php';
require_once CORE_PATH . 'model/update.php';
require_once CORE_PATH . 'model/delete.php';

/**
 * Base case for data-layer tests. Each test starts from a fresh in-memory
 * SQLite database with a known schema and injected connection/prefix, and
 * resets the seam in tearDown so tests stay isolated.
 */
abstract class OrTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
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

    protected function seedItems(): void
    {
        $this->pdo->exec("INSERT INTO items (name, category, qty) VALUES ('alpha', 'one', 1)");
        $this->pdo->exec("INSERT INTO items (name, category, qty) VALUES ('beta', 'one', 2)");
        $this->pdo->exec("INSERT INTO items (name, category, qty) VALUES ('gamma', 'two', 3)");
    }
}
