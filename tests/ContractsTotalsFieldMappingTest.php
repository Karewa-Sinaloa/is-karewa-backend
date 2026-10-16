<?php

use PHPUnit\Framework\TestCase;

require_once CORE_PATH . 'helpers/utilities.php';
require_once CORE_PATH . 'helpers/custom_exceptions.php';
require_once CORE_PATH . 'bootstrap/midelware.php';
require_once ROOT_PATH . 'api/contracts/controller.php';

/**
 * Guards the contracts module totals declaration: the monetary sums must map
 * to real module columns (total_amount with IVA, subtotal without IVA) and the
 * groupings must reference the declared joins (c.fiscal_year for the fiscal
 * year, c_periods for the period nested inside each fiscal year).
 */
final class ContractsTotalsFieldMappingTest extends TestCase
{
    protected function setUp(): void
    {
        global $_payload;
        $_payload = new stdClass();
        $_GET['id'] = null;

        if (!defined('REQUEST_TYPE')) {
            define('REQUEST_TYPE', 'index');
        }
    }

    private static function getParams(object $controller): array
    {
        $property = new ReflectionProperty($controller, 'get_params');
        return $property->getValue($controller);
    }

    public function testContractsDeclaresItsMonetaryTotals(): void
    {
        $params  = self::getParams(new contractsComponent());
        $totals  = $params['totals'] ?? null;

        $this->assertIsArray($totals, 'The contracts module must declare totals');
        $this->assertSame(
            ['total_amount', 'subtotal'],
            array_keys($totals['sums']),
            'The general sums must be total_amount (with IVA) and subtotal (without IVA)'
        );
    }

    public function testEverySumMapsToARealModuleColumn(): void
    {
        $module = new contractsComponent();
        $params = self::getParams($module);

        $fieldsProperty = new ReflectionProperty(contractsComponent::class, 'moduleFields');
        $moduleFields = $fieldsProperty->getValue($module);
        $columns = array_column($moduleFields, 'field');

        foreach ($params['totals']['sums'] as $alias => $expression) {
            $this->assertContains($expression, $columns, "Sum {$alias} maps to a non-existent module column");
        }
    }

    public function testTotalAmountAndSubtotalMapToTheRealColumns(): void
    {
        $params = self::getParams(new contractsComponent());

        $this->assertSame('c.total_amount', $params['totals']['sums']['total_amount']);
        $this->assertSame('c.subtotal', $params['totals']['sums']['subtotal']);
    }

    public function testGroupingsReferenceTheDeclaredJoinsAndColumns(): void
    {
        $params = self::getParams(new contractsComponent());
        $groups = $params['totals']['groups'];

        $this->assertSame(['by_fiscal_year', 'by_fiscal_year_period', 'by_procedure'], array_keys($groups), 'The fiscal year grouping, its nested period grouping and the procedure type grouping must be declared');

        $this->assertSame(['c.fiscal_year'], $groups['by_fiscal_year']['group_by']);
        $this->assertSame('c.fiscal_year', $groups['by_fiscal_year']['fields']['fiscal_year']);
        $this->assertSame('c.total_amount', $groups['by_fiscal_year']['sums']['total_amount']);

        $this->assertSame(
            ['c.fiscal_year', 'c.period_id', 'per.name'],
            $groups['by_fiscal_year_period']['group_by'],
            'The nested period grouping must group on fiscal year, period id and its joined name'
        );
        $this->assertSame('c.period_id', $groups['by_fiscal_year_period']['fields']['period_id']);
        $this->assertSame('per.name', $groups['by_fiscal_year_period']['fields']['period_name']);
        $this->assertSame('c.total_amount', $groups['by_fiscal_year_period']['sums']['total_amount']);

        $this->assertSame(
            ['into' => 'by_fiscal_year', 'by' => 'fiscal_year', 'as' => 'periods'],
            $groups['by_fiscal_year_period']['nest'],
            'Period rows must nest under the fiscal year entry, never as a top-level grouping'
        );
    }

    public function testThePeriodJoinIsRequiredByTheGrouping(): void
    {
        $params = self::getParams(new contractsComponent());

        $joins = array_filter($params['joins'], function ($join) {
            return str_starts_with((string) $join['table'], 'c_periods');
        });

        $this->assertNotEmpty($joins, 'The nested period grouping needs the c_periods join declared in get_params');
    }

    public function testTheProcedureJoinIsRequiredByTheGrouping(): void
    {
        $params = self::getParams(new contractsComponent());

        $joins = array_filter($params['joins'], function ($join) {
            return str_starts_with((string) $join['table'], 'c_procedures');
        });

        $this->assertNotEmpty($joins, 'The by_procedure grouping needs the c_procedures join declared in get_params');
    }

    public function testProcedureTypeTotalsAreDeclaredWithIvaOnly(): void
    {
        $params = self::getParams(new contractsComponent());
        $group  = $params['totals']['groups']['by_procedure'] ?? null;

        $this->assertIsArray($group, 'The contracts module must declare a by_procedure grouping');
        $this->assertSame(['c.procedure_id', 'p.name'], $group['group_by']);
        $this->assertSame('c.procedure_id', $group['fields']['procedure_id']);
        $this->assertSame('p.name', $group['fields']['procedure_name']);
        $this->assertSame(
            ['total_amount' => 'c.total_amount'],
            $group['sums'],
            'Procedure type totals are with-IVA only: total_amount, never subtotal'
        );
    }

    public function testNoStandalonePeriodGroupingIsDeclared(): void
    {
        $params = self::getParams(new contractsComponent());

        $this->assertArrayNotHasKey(
            'by_period',
            $params['totals']['groups'],
            'A top-level period grouping must not be declared; periods live inside by_fiscal_year'
        );
    }
}
