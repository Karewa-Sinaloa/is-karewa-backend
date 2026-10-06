<?php

require_once __DIR__ . '/Support/OrTestCase.php';

use App\Model\DB;
use App\Model\DBDelete;
use App\Model\DBStore;
use App\Model\DBUpdate;

/**
 * Covers the write side of the data layer: insert ids, affected-row counts for
 * update/delete, and prefix routing through the DB seam.
 */
final class OrWriteTest extends OrTestCase
{
    private function rowCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
    }

    public function testInsertReturnsTheInsertedId(): void
    {
        $id = DBStore::Store('items', [[
            ['name', 'alpha'],
            ['category', 'one'],
            ['qty', 1],
        ]]);

        $this->assertSame(1, (int) $id, 'Store must return the last insert id');
        $this->assertSame(1, $this->rowCount());

        $name = $this->pdo->query('SELECT name FROM items WHERE id = 1')->fetchColumn();
        $this->assertSame('alpha', $name);
    }

    public function testInsertBindsValuesInsteadOfInliningThem(): void
    {
        DBStore::Store('items', [[
            ['name', "alpha'; DROP TABLE items; --"],
            ['category', 'one'],
            ['qty', 1],
        ]]);

        $name = $this->pdo->query('SELECT name FROM items WHERE id = 1')->fetchColumn();
        $this->assertSame("alpha'; DROP TABLE items; --", $name, 'The value must be stored verbatim as data');
        $this->assertSame(1, $this->rowCount(), 'The table must still exist');
    }

    public function testUpdateReportsAffectedRows(): void
    {
        $this->seedItems(); // 3 rows

        $affected = DBUpdate::Update('items', [['qty', 99, 'set']], [['id', '1', '=']]);

        $this->assertSame(1, (int) $affected);
        $qty = $this->pdo->query('SELECT qty FROM items WHERE id = 1')->fetchColumn();
        $this->assertSame(99, (int) $qty);

        $none = DBUpdate::Update('items', [['qty', 5, 'set']], [['id', '999', '=']]);
        $this->assertSame(0, (int) $none, 'An update matching no row must report 0');
    }

    public function testDeleteReportsAffectedRows(): void
    {
        $this->seedItems();

        $affected = DBDelete::delete('items', [['id', '1', '=']]);

        $this->assertSame(1, (int) $affected);
        $this->assertSame(2, $this->rowCount());

        $again = DBDelete::delete('items', [['id', '1', '=']]);
        $this->assertSame(0, (int) $again, 'Deleting a missing row must report 0');
    }

    public function testPrefixedTableNamesResolveToThePrefixedTable(): void
    {
        DB::setPrefix('pfx_');
        $this->pdo->exec(
            'CREATE TABLE pfx_items ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' name TEXT NOT NULL,'
            . ' category TEXT,'
            . ' qty INTEGER DEFAULT 0'
            . ')'
        );

        $id = DBStore::Store('items', [[
            ['name', 'prefixed'],
            ['category', 'one'],
            ['qty', 1],
        ]]);

        $inPrefixed = (int) $this->pdo->query('SELECT COUNT(*) FROM pfx_items')->fetchColumn();
        $inPlain = (int) $this->pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();

        $this->assertSame(1, (int) $id);
        $this->assertSame(1, $inPrefixed, 'The write must land in the prefixed table');
        $this->assertSame(0, $inPlain, 'The unprefixed table must stay untouched');

        $affected = DBDelete::delete('items', [['id', '1', '=']]);
        $this->assertSame(1, (int) $affected, 'Delete must resolve the prefixed table too');
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM pfx_items')->fetchColumn());
    }
}
