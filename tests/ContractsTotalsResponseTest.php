<?php

use PHPUnit\Framework\TestCase;

/**
 * Exercises the embedded totals block of the contracts listing against a
 * seeded SQLite database: general amounts cover the whole filtered set (not
 * the requested page), each fiscal year carries its own nested per-period
 * entries (never a top-level period grouping), each procedure type present in
 * the results carries its own with-IVA total (unused types stay absent),
 * filters and search restrict the totals, contracts with a NULL period or
 * fiscal year still count, an empty result set still answers the success
 * envelope with zeroed totals, and the block is absent without embed=totals.
 *
 * Every scenario ends on ApiResponse::Set(), which terminates the request, so
 * each one runs in a subprocess (the pattern of tests/ConfigVisibilityDataTest.php).
 */
final class ContractsTotalsResponseTest extends TestCase
{
    /**
     * Run a script in a fresh PHP process and return the terminating response.
     *
     * @return array<string, mixed>
     */
    private function runScenario(string $script): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ctr_') . '.php';
        file_put_contents($tmp, "<?php\n" . $script);

        $vendor = dirname(CORE_PATH) . '/../vendor';
        $vendor = realpath($vendor) ?: $vendor;
        $cmd = 'CTRT_VENDOR=' . escapeshellarg($vendor) . ' '
             . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1';
        $output = (string) shell_exec($cmd);
        @unlink($tmp);

        $json = null;
        foreach ($this->extractJsonObjects($output) as $decoded) {
            if (array_key_exists('http_code', $decoded)) {
                $json = $decoded;
            }
        }
        $this->assertIsArray($json, 'Raw: ' . $output);
        return $json;
    }

    /**
     * Extract every top-level JSON object from a possibly concatenated output.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractJsonObjects(string $raw): array
    {
        $objects = [];
        $depth   = 0;
        $start   = null;
        $len     = strlen($raw);
        for ($i = 0; $i < $len; $i++) {
            $ch = $raw[$i];
            if ($ch === '{') {
                if ($depth === 0) {
                    $start = $i;
                }
                $depth++;
            } elseif ($ch === '}' && $depth > 0) {
                $depth--;
                if ($depth === 0 && $start !== null) {
                    $decoded = json_decode(substr($raw, $start, $i - $start + 1), true);
                    if (is_array($decoded)) {
                        $objects[] = $decoded;
                    }
                    $start = null;
                }
            }
        }
        return $objects;
    }

    /**
     * Query the contracts module against a seeded SQLite database.
     *
     * Seeded contracts:
     *   1: procedure 1, period 1o, fiscal 2024, total_amount 100.00, subtotal  90.91, FALCON-1
     *   2: procedure 2, period 1o, fiscal 2024, total_amount 200.50, subtotal 180.45, ALPHA-2
     *   3: procedure 1, period 2o, fiscal 2025, total_amount 300.25, subtotal 270.23, FALCON-3
     *   4: procedure 2, period 2o, fiscal 2025, total_amount 400.00, subtotal 360.00, BETA-4
     *
     * Seeded procedures: 1 Licitación Pública, 2 Adjudicación Directa,
     * 3 Invitación a Terceros (no contract uses type 3).
     *
     * @param array<string, mixed> $get
     * @param string|null $extra_seed Extra INSERT statement for this scenario only
     */
    private function query(array $get, ?string $extra_seed = null): array
    {
        $core      = CORE_PATH;
        $api       = dirname($core) . '/api/contracts/controller.php';
        $get_php   = var_export($get, true);
        $extra_php = var_export($extra_seed, true);

        $script = <<<PHP
define('CORE_PATH', '{$core}');
define('MODULE', 'contracts');
define('REQUEST_TYPE', 'index');
define('IDENTIFIER_UID', 'test-uid');
define('ERROR_LOG_FILE', '/tmp/ctr_error.log');
define('DEBUG_LOG_FILE', '/tmp/ctr_debug.log');
define('DEBUG_LOG_PATH', '/tmp');
if (!file_exists(DEBUG_LOG_FILE)) { @touch(DEBUG_LOG_FILE); }
error_reporting(E_ERROR);
require_once getenv('CTRT_VENDOR') . '/autoload.php';
require_once '{$core}helpers/log.manager.php';
require_once '{$core}helpers/custom_exceptions.php';
require_once '{$core}model/conexion.php';
require_once '{$core}model/get.php';
require_once '{$core}helpers/api_response.php';
require_once '{$core}bootstrap/midelware.php';
require_once '{$api}';

\$pdo = new PDO('sqlite::memory:');
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
\$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
\$pdo->exec('CREATE TABLE contratos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    procedure_id INTEGER, subject_id INTEGER,
    admin_period_start TEXT, admin_period_end TEXT,
    fiscal_year INTEGER, period_id INTEGER, contract_id TEXT,
    partida_type INTEGER, partida_id INTEGER, status_id INTEGER,
    call_link TEXT, call_date TEXT, work_description TEXT,
    clarification_meeting_date TEXT, proposals_url TEXT, proposal_url TEXT,
    provider_id INTEGER, organizer_admin_unit_id INTEGER,
    applicant_admin_unit_id INTEGER, admin_unit_type_id INTEGER,
    contract_number TEXT, contract_date TEXT, contract_type_id INTEGER,
    total_amount REAL DEFAULT 0, min_amount REAL DEFAULT 0,
    max_amount REAL DEFAULT 0, subtotal REAL DEFAULT 0,
    contract_link TEXT, area_in_charge TEXT, contract_updated_at TEXT,
    notes TEXT, organization_notes TEXT, information_date TEXT,
    amount_was_exceeded INTEGER, exceeded_amount REAL,
    contract_backup TEXT, announcement_backup TEXT,
    created_at TEXT, updated_at TEXT)');
foreach (['c_procedures', 'c_materia', 'c_partidas', 'c_estatus', 'proveedores',
          'admin_units', 'unit_types', 'c_tipo'] as \$t) {
    \$pdo->exec("CREATE TABLE {\$t} (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)");
}
\$pdo->exec('CREATE TABLE c_periods (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, slug TEXT)');
\$pdo->exec("INSERT INTO c_periods (id, name, slug) VALUES (1, '1o', 'one-quarter'), (2, '2o', 'two-quarter')");
\$pdo->exec("INSERT INTO c_procedures (id, name) VALUES
    (1, 'Licitación Pública'),
    (2, 'Adjudicación Directa'),
    (3, 'Invitación a Terceros')");
\$pdo->exec("INSERT INTO contratos (id, procedure_id, period_id, fiscal_year, total_amount, subtotal, contract_number, status_id) VALUES
    (1, 1, 1, 2024, 100.00,  90.91, 'FALCON-1', 1),
    (2, 2, 1, 2024, 200.50, 180.45, 'ALPHA-2',  1),
    (3, 1, 2, 2025, 300.25, 270.23, 'FALCON-3', 2),
    (4, 2, 2, 2025, 400.00, 360.00, 'BETA-4',   2)");
if ({$extra_php} !== null) { \$pdo->exec({$extra_php}); }
App\\Model\\DB::setConnection(\$pdo);
App\\Model\\DB::setPrefix('');

\$_GET = array_merge(
    ['page' => '1', 'limit' => '10', 'fields' => '', 'search' => '', 'sort' => '', 'groupby' => '', 'embed' => ''],
    {$get_php}
);

\$component = new contractsComponent();
\$component->index();
PHP;

        return $this->runScenario($script);
    }

    /**
     * Find one entry of a totals group list matching a column value.
     *
     * @param array<int, array<string, mixed>> $entries
     * @return array<string, mixed>|null
     */
    private function findEntry(array $entries, string $column, $value): ?array
    {
        foreach ($entries as $entry) {
            if (($entry[$column] ?? null) == $value) {
                return $entry;
            }
        }
        return null;
    }

    public function testTotalsCoverTheWholeFilteredSetNotJustTheRequestedPage(): void
    {
        $result = $this->query(['embed' => 'totals,pagination', 'limit' => '1']);

        $this->assertSame(200, $result['http_code'], 'RAW: ' . json_encode($result));
        $this->assertCount(1, $result['data'], 'The page itself must hold one row');
        $this->assertSame(4, $result['pagination']['results']);
        $totals = $result['totals'] ?? null;
        $this->assertIsArray($totals, 'RAW: ' . json_encode($result));
        $this->assertSame(4, $totals['results'], 'The count must ignore pagination');
        $this->assertEqualsWithDelta(1000.75, (float) $totals['total_amount'], 0.001);
        $this->assertEqualsWithDelta(901.59, (float) $totals['subtotal'], 0.001);
    }

    public function testTotalsNestPeriodsInsideEachFiscalYear(): void
    {
        $result = $this->query(['embed' => 'totals']);

        $this->assertSame(200, $result['http_code'], 'RAW: ' . json_encode($result));
        $totals = $result['totals'];

        $this->assertArrayNotHasKey(
            'by_period',
            $totals,
            'No standalone top-level period grouping: ' . json_encode($totals)
        );

        $this->assertCount(2, $totals['by_fiscal_year'], 'One entry per fiscal year: ' . json_encode($totals));

        $y2024 = $this->findEntry($totals['by_fiscal_year'], 'fiscal_year', 2024);
        $this->assertIsArray($y2024, 'RAW: ' . json_encode($totals));
        $this->assertEqualsWithDelta(300.50, (float) $y2024['total_amount'], 0.001);
        $this->assertEqualsWithDelta(271.36, (float) $y2024['subtotal'], 0.001);
        $this->assertCount(1, $y2024['periods'], 'Periods nested in 2024: ' . json_encode($y2024));

        $p2024 = $this->findEntry($y2024['periods'], 'period_id', 1);
        $this->assertIsArray($p2024, 'RAW: ' . json_encode($y2024));
        $this->assertSame('1o', $p2024['period_name']);
        $this->assertEqualsWithDelta(300.50, (float) $p2024['total_amount'], 0.001);
        $this->assertEqualsWithDelta(271.36, (float) $p2024['subtotal'], 0.001);

        $y2025 = $this->findEntry($totals['by_fiscal_year'], 'fiscal_year', 2025);
        $this->assertIsArray($y2025, 'RAW: ' . json_encode($totals));
        $this->assertEqualsWithDelta(700.25, (float) $y2025['total_amount'], 0.001);
        $this->assertEqualsWithDelta(630.23, (float) $y2025['subtotal'], 0.001);
        $this->assertCount(1, $y2025['periods'], 'Periods nested in 2025: ' . json_encode($y2025));

        $p2025 = $this->findEntry($y2025['periods'], 'period_id', 2);
        $this->assertIsArray($p2025, 'RAW: ' . json_encode($y2025));
        $this->assertSame('2o', $p2025['period_name']);
        $this->assertEqualsWithDelta(700.25, (float) $p2025['total_amount'], 0.001);
        $this->assertEqualsWithDelta(630.23, (float) $p2025['subtotal'], 0.001);
    }

    public function testSamePeriodAcrossFiscalYearsReportsIndependentAmounts(): void
    {
        // Extra contract: period 1o also belongs to fiscal year 2023, so the
        // same period id appears under two fiscal years with independent sums.
        $extra = "INSERT INTO contratos (id, period_id, fiscal_year, total_amount, subtotal, contract_number, status_id)
            VALUES (5, 1, 2023, 75.00, 60.00, 'GAMMA-5', 1)";
        $result = $this->query(['embed' => 'totals'], $extra);

        $this->assertSame(200, $result['http_code'], 'RAW: ' . json_encode($result));
        $totals = $result['totals'];

        $y2023 = $this->findEntry($totals['by_fiscal_year'], 'fiscal_year', 2023);
        $this->assertIsArray($y2023, 'RAW: ' . json_encode($totals));
        $p2023 = $this->findEntry($y2023['periods'], 'period_id', 1);
        $this->assertIsArray($p2023, 'RAW: ' . json_encode($y2023));
        $this->assertEqualsWithDelta(75.00, (float) $p2023['total_amount'], 0.001);

        $y2024 = $this->findEntry($totals['by_fiscal_year'], 'fiscal_year', 2024);
        $this->assertIsArray($y2024, 'RAW: ' . json_encode($totals));
        $p2024 = $this->findEntry($y2024['periods'], 'period_id', 1);
        $this->assertIsArray($p2024, 'RAW: ' . json_encode($y2024));
        $this->assertEqualsWithDelta(300.50, (float) $p2024['total_amount'], 0.001);
    }

    public function testContractsWithoutPeriodOrFiscalYearStillCount(): void
    {
        // Historical rows may carry NULL period_id / fiscal_year; their
        // amounts must not disappear from the general totals nor from the
        // fiscal-year grouping.
        $extra = "INSERT INTO contratos (id, period_id, fiscal_year, total_amount, subtotal, contract_number, status_id)
            VALUES (5, NULL, NULL, 50.00, 40.00, 'NULL-5', 1)";
        $result = $this->query(['embed' => 'totals'], $extra);

        $this->assertSame(200, $result['http_code'], 'RAW: ' . json_encode($result));
        $totals = $result['totals'];
        $this->assertSame(5, $totals['results'], 'RAW: ' . json_encode($totals));
        $this->assertEqualsWithDelta(1050.75, (float) $totals['total_amount'], 0.001);
        $this->assertEqualsWithDelta(941.59, (float) $totals['subtotal'], 0.001);

        $null_year = $this->findEntry($totals['by_fiscal_year'], 'fiscal_year', null);
        $this->assertIsArray($null_year, 'RAW: ' . json_encode($totals));
        $this->assertEqualsWithDelta(50.00, (float) $null_year['total_amount'], 0.001);
        $this->assertCount(1, $null_year['periods'], 'RAW: ' . json_encode($null_year));
        $this->assertNull($null_year['periods'][0]['period_id']);
        $this->assertEqualsWithDelta(50.00, (float) $null_year['periods'][0]['total_amount'], 0.001);
    }

    public function testTotalsHonorFilters(): void
    {
        $result = $this->query(['embed' => 'totals', 'fiscal_year' => 'eq:2024']);

        $this->assertSame(200, $result['http_code'], 'RAW: ' . json_encode($result));
        $totals = $result['totals'];
        $this->assertSame(2, $totals['results'], 'Only the filtered contracts count');
        $this->assertEqualsWithDelta(300.50, (float) $totals['total_amount'], 0.001);
        $this->assertEqualsWithDelta(271.36, (float) $totals['subtotal'], 0.001);
        $this->assertCount(1, $totals['by_fiscal_year'], 'Only the filtered year appears: ' . json_encode($totals));
        $y2024 = $this->findEntry($totals['by_fiscal_year'], 'fiscal_year', 2024);
        $this->assertIsArray($y2024, 'RAW: ' . json_encode($totals));
        $this->assertCount(1, $y2024['periods'], 'Only the period of the filtered rows appears');
    }

    public function testTotalsHonorSearch(): void
    {
        $result = $this->query(['embed' => 'totals', 'search' => 'falcon']);

        $this->assertSame(200, $result['http_code'], 'RAW: ' . json_encode($result));
        $totals = $result['totals'];
        $this->assertSame(2, $totals['results'], 'FALCON-1 and FALCON-3 match the search');
        $this->assertEqualsWithDelta(400.25, (float) $totals['total_amount'], 0.001);
        $this->assertEqualsWithDelta(361.14, (float) $totals['subtotal'], 0.001);
    }

    public function testEmptyResultSetStillReturnsZeroedTotals(): void
    {
        $result = $this->query(['embed' => 'totals', 'status_id' => 'eq:999']);

        $this->assertSame(200, $result['http_code'], 'RAW: ' . json_encode($result));
        $this->assertSame([], $result['data']);
        $totals = $result['totals'];
        $this->assertSame(0, $totals['results']);
        $this->assertSame('0.00', $totals['total_amount']);
        $this->assertSame('0.00', $totals['subtotal']);
        $this->assertArrayNotHasKey('by_period', $totals, 'RAW: ' . json_encode($totals));
        $this->assertSame([], $totals['by_fiscal_year'], 'RAW: ' . json_encode($totals));
        $this->assertSame([], $totals['by_procedure'], 'RAW: ' . json_encode($totals));
    }

    public function testTotalsGroupByProcedureTypeWithIvaOnly(): void
    {
        $result = $this->query(['embed' => 'totals']);

        $this->assertSame(200, $result['http_code'], 'RAW: ' . json_encode($result));
        $totals = $result['totals'];

        $this->assertArrayHasKey('by_procedure', $totals, 'RAW: ' . json_encode($totals));
        $this->assertCount(2, $totals['by_procedure'], 'One entry per procedure type present in the results: ' . json_encode($totals));

        $licitacion = $this->findEntry($totals['by_procedure'], 'procedure_id', 1);
        $this->assertIsArray($licitacion, 'RAW: ' . json_encode($totals));
        $this->assertSame('Licitación Pública', $licitacion['procedure_name']);
        $this->assertEqualsWithDelta(400.25, (float) $licitacion['total_amount'], 0.001, 'Contracts 1 and 3, with IVA');
        $this->assertArrayNotHasKey('subtotal', $licitacion, 'Procedure totals are with-IVA only: ' . json_encode($licitacion));

        $adjudicacion = $this->findEntry($totals['by_procedure'], 'procedure_id', 2);
        $this->assertIsArray($adjudicacion, 'RAW: ' . json_encode($totals));
        $this->assertSame('Adjudicación Directa', $adjudicacion['procedure_name']);
        $this->assertEqualsWithDelta(600.50, (float) $adjudicacion['total_amount'], 0.001, 'Contracts 2 and 4, with IVA');
        $this->assertArrayNotHasKey('subtotal', $adjudicacion, 'Procedure totals are with-IVA only: ' . json_encode($adjudicacion));
    }

    public function testProcedureTotalsIgnorePagination(): void
    {
        $result = $this->query(['embed' => 'totals,pagination', 'limit' => '1']);

        $this->assertSame(200, $result['http_code'], 'RAW: ' . json_encode($result));
        $this->assertCount(1, $result['data'], 'The page itself must hold one row');
        $totals = $result['totals'];

        $this->assertCount(2, $totals['by_procedure'], 'RAW: ' . json_encode($totals));
        $licitacion = $this->findEntry($totals['by_procedure'], 'procedure_id', 1);
        $this->assertIsArray($licitacion, 'RAW: ' . json_encode($totals));
        $this->assertEqualsWithDelta(400.25, (float) $licitacion['total_amount'], 0.001, 'All four contracts count, not the one on the page');
        $adjudicacion = $this->findEntry($totals['by_procedure'], 'procedure_id', 2);
        $this->assertIsArray($adjudicacion, 'RAW: ' . json_encode($totals));
        $this->assertEqualsWithDelta(600.50, (float) $adjudicacion['total_amount'], 0.001);
    }

    public function testProcedureTotalsHonorFiltersAndSearch(): void
    {
        $result = $this->query(['embed' => 'totals', 'fiscal_year' => 'eq:2024']);

        $this->assertSame(200, $result['http_code'], 'RAW: ' . json_encode($result));
        $totals = $result['totals'];
        $this->assertCount(2, $totals['by_procedure'], 'RAW: ' . json_encode($totals));
        $licitacion = $this->findEntry($totals['by_procedure'], 'procedure_id', 1);
        $this->assertIsArray($licitacion, 'RAW: ' . json_encode($totals));
        $this->assertEqualsWithDelta(100.00, (float) $licitacion['total_amount'], 0.001, 'Only contract 1 is in fiscal year 2024');
        $adjudicacion = $this->findEntry($totals['by_procedure'], 'procedure_id', 2);
        $this->assertIsArray($adjudicacion, 'RAW: ' . json_encode($totals));
        $this->assertEqualsWithDelta(200.50, (float) $adjudicacion['total_amount'], 0.001, 'Only contract 2 is in fiscal year 2024');

        $result = $this->query(['embed' => 'totals', 'search' => 'falcon']);

        $this->assertSame(200, $result['http_code'], 'RAW: ' . json_encode($result));
        $totals = $result['totals'];
        $this->assertCount(1, $totals['by_procedure'], 'Only FALCON-1 and FALCON-3 match, both procedure 1: ' . json_encode($totals));
        $licitacion = $this->findEntry($totals['by_procedure'], 'procedure_id', 1);
        $this->assertIsArray($licitacion, 'RAW: ' . json_encode($totals));
        $this->assertEqualsWithDelta(400.25, (float) $licitacion['total_amount'], 0.001);
    }

    public function testProcedureTypesWithoutContractsAreAbsent(): void
    {
        // Type 3 (Invitación a Terceros) is seeded in c_procedures but no
        // contract uses it: it must not appear in the grouping.
        $result = $this->query(['embed' => 'totals']);

        $this->assertSame(200, $result['http_code'], 'RAW: ' . json_encode($result));
        $totals = $result['totals'];
        $this->assertCount(2, $totals['by_procedure'], 'RAW: ' . json_encode($totals));
        $this->assertNull(
            $this->findEntry($totals['by_procedure'], 'procedure_id', 3),
            'A procedure type without matching contracts must stay absent: ' . json_encode($totals)
        );
    }

    public function testResponseWithoutTheEmbedOmitsTotals(): void
    {
        $result = $this->query(['embed' => 'pagination']);

        $this->assertSame(200, $result['http_code'], 'RAW: ' . json_encode($result));
        $this->assertCount(4, $result['data']);
        $this->assertArrayNotHasKey('totals', $result, 'Raw: ' . json_encode($result));
        $this->assertSame(4, $result['pagination']['results']);
    }
}
