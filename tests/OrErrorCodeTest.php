<?php

if (!defined('DEBUG_LOG_PATH')) {
    define('DEBUG_LOG_PATH', sys_get_temp_dir() . '/karewa_orm_logs');
}
if (!defined('DEBUG_LOG_FILE')) {
    define('DEBUG_LOG_FILE', DEBUG_LOG_PATH . '/or_error_code.log');
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
use App\Model\DBGet;

/**
 * Guards the data-layer error contract: an AppException raised during a
 * statement keeps its domain code, while a genuine SQL/driver failure is
 * reported with the distinct database code 902003 instead of the generic one.
 */
final class OrErrorCodeTest extends OrTestCase
{
    public function testDomainExceptionKeepsItsCode(): void
    {
        $throwing = new class('sqlite::memory:') extends PDO {
            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                throw new \AppException('This element is associated with another table', 902002);
            }
        };
        DB::setConnection($throwing);

        try {
            DBGet::Get(['table' => 'items', 'filters' => [], 'joins' => []], 'list');
            $this->fail('A domain AppException raised inside the layer must propagate');
        } catch (\AppException $e) {
            $this->assertSame(902002, (int) $e->getCode(), 'The domain code must be preserved unchanged');
            $this->assertNotSame(902003, (int) $e->getCode());
        }
    }

    public function testSqlFailureYieldsTheDatabaseCode(): void
    {
        try {
            DBGet::Get(['table' => 'missing_table', 'filters' => [], 'joins' => []], 'list');
            $this->fail('Preparing a statement against a missing table must fail');
        } catch (\AppException $e) {
            $this->assertSame(902003, (int) $e->getCode(), 'A SQL failure must use the distinct database code');
            $this->assertStringContainsString('missing_table', $e->getMessage(), 'The original message must be retained');
        }
    }

    public function testTheNewCodeIsNotTheGenericCode(): void
    {
        $yaml = file_get_contents(CORE_PATH . 'config/api_codes.yml');

        $this->assertStringContainsString('902003:', $yaml);
        $this->assertStringContainsString('902000:', $yaml, 'The existing generic code must be kept');
    }
}
