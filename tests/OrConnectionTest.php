<?php

use PHPUnit\Framework\TestCase;
use App\Model\DB;

require_once CORE_PATH . 'model/conexion.php';

/**
 * Guards that the data layer reuses one connection per request while still
 * honoring an injected connection.
 */
final class OrConnectionTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::reset();
    }

    public function testInjectedConnectionTakesPrecedence(): void
    {
        $injected = new PDO('sqlite::memory:');
        $this->seedCachedConnection(new PDO('sqlite::memory:'));
        DB::setConnection($injected);

        $this->assertSame($injected, DB::connection());
        $this->assertSame($injected, DB::connection());
    }

    public function testRepeatedCallsReuseTheCachedConnection(): void
    {
        $cached = new PDO('sqlite::memory:');
        $this->seedCachedConnection($cached);

        $this->assertSame($cached, DB::connection());
        $this->assertSame($cached, DB::connection());
    }

    private function seedCachedConnection(PDO $connection): void
    {
        $property = new ReflectionProperty(DB::class, 'connection');
        $property->setValue(null, $connection);
    }
}
