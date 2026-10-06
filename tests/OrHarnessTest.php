<?php

require_once __DIR__ . '/Support/OrTestCase.php';

use App\Model\DB;

/**
 * Guards the test harness itself: each test starts from a fresh in-memory
 * database, layer operations run on the injected connection, and the prefix
 * used by the layer is empty for tests.
 */
final class OrHarnessTest extends OrTestCase
{
    public function testTrivialQueryRunsAgainstTheInMemoryDatabase(): void
    {
        $this->pdo->exec("INSERT INTO items (name, category, qty) VALUES ('alpha', 'one', 1)");

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();

        $this->assertSame(1, $count, 'The base case must provide a queryable in-memory database');
    }

    public function testTheInjectedConnectionIsUsedByTheLayer(): void
    {
        $this->assertSame($this->pdo, DB::connection(), 'The layer must run on the injected connection');
    }

    public function testThePrefixIsEmptyForTests(): void
    {
        $this->assertSame('', DB::prefix(), 'Tests must query unprefixed tables');
    }

    public function testSeedFromAPreviousTestDoesNotLeakIntoThisTest(): void
    {
        // testTrivialQueryRunsAgainstTheInMemoryDatabase inserts a row; if the
        // harness leaked state, this test would see it.
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();

        $this->assertSame(0, $count, 'Every test must start from a clean database');
    }

    public function testTheKnownSchemaIsCreated(): void
    {
        $tables = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name"
        )->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('items', $tables);
        $this->assertContains('categories', $tables);
    }
}
