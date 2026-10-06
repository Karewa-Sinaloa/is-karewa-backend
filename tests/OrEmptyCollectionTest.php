<?php

if (!defined('DEBUG_LOG_PATH')) {
    define('DEBUG_LOG_PATH', sys_get_temp_dir() . '/karewa_orm_logs');
}
if (!defined('DEBUG_LOG_FILE')) {
    define('DEBUG_LOG_FILE', DEBUG_LOG_PATH . '/or_empty_collection.log');
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
require_once CORE_PATH . 'bootstrap/midelware.php';

use App\Model\BaseModel;

/**
 * Minimal module used to drive BaseModel::get() against the in-memory schema
 * with responses routed through the raw-data path (end = false) so the test
 * can observe the returned array instead of a terminated response.
 */
final class EmptyCollectionModule extends BaseModel
{
    protected $moduleFields = [
        'id'   => ['field' => 'id', 'saved' => false],
        'name' => ['field' => 'name'],
    ];

    protected $get_params = [
        'table'   => 'items',
        'filters' => [],
        'joins'   => [],
        'search'  => [],
    ];

    public function __construct()
    {
        global $_payload;
        $_payload = new stdClass();
        parent::__construct();
        $this->methodOptions['end'] = false;
    }
}

/**
 * Guards the empty-result contract: an empty list read is a success with an
 * empty data array, while a missing single record is still not-found.
 */
final class OrEmptyCollectionTest extends OrTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_GET = [
            'page'   => 1,
            'limit'  => 10,
            'fields' => '',
            'sort'   => '',
            'search' => '',
            'embed'  => '',
            'id'     => null,
        ];
    }

    protected function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }

    public function testEmptyIndexReturnsSuccessWithEmptyArray(): void
    {
        $result = (new EmptyCollectionModule())->get();

        $this->assertSame(['data' => []], $result, 'An empty list must be a success with data: []');
    }

    public function testEmptyIndexSkipsTheNotFoundPath(): void
    {
        try {
            (new EmptyCollectionModule())->get();
        } catch (\AppException $e) {
            $this->fail('An empty list must not raise the not-found error, got code ' . $e->getCode());
        }

        $this->addToAssertionCount(1);
    }

    public function testEmptyShowStillReportsNotFound(): void
    {
        $_GET['id'] = '999';

        try {
            (new EmptyCollectionModule())->get();
            $this->fail('A missing single record must report not-found');
        } catch (\AppException $e) {
            $this->assertSame(404000, (int) $e->getCode());
        }
    }

    public function testEmptyShowWithRowsStillReportsNotFoundForMissingId(): void
    {
        $this->seedItems();
        $_GET['id'] = '999';

        try {
            (new EmptyCollectionModule())->get();
            $this->fail('A single record that does not exist must report not-found');
        } catch (\AppException $e) {
            $this->assertSame(404000, (int) $e->getCode());
        }
    }
}
