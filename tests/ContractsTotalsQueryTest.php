<?php

require_once __DIR__ . '/Support/OrTestCase.php';

use App\Model\DBGet;

/**
 * Guards the ORM aggregation action 'sum': it reuses the same joins/filters
 * pipeline as list/count, returns ROUND(SUM(field), 2) per declared sum plus a
 * COUNT(*) of the matching rows, never applies LIMIT, and supports fixed
 * GROUP BY expressions (one row per group with its group column and sums).
 */
final class ContractsTotalsQueryTest extends OrTestCase
{
    public function testSumReturnsRoundedTotalAndRowCount(): void
    {
        $this->seedItems(); // qty: 1, 2, 3

        $totals = DBGet::Get([
            'table'   => 'items',
            'filters' => [],
            'joins'   => [],
            'sums'    => ['qty_total' => 'qty'],
        ], 'sum');

        $this->assertSame(3, (int) $totals['results'], 'The count must be the number of matching rows');
        $this->assertEqualsWithDelta(6.0, (float) $totals['sums']['qty_total'], 0.001, 'The sum must add every matching row');
    }

    public function testSumIgnoresPaginationParameters(): void
    {
        $this->seedItems();

        // A page of 1 row must not shrink the aggregation: the totals belong
        // to the whole filtered set.
        $totals = DBGet::Get([
            'table'       => 'items',
            'filters'     => [],
            'joins'       => [],
            'sums'        => ['qty_total' => 'qty'],
            'max_results' => 1,
            'page'        => 1,
        ], 'sum');

        $this->assertSame(3, (int) $totals['results'], 'The count must cover the whole set, not the page');
        $this->assertEqualsWithDelta(6.0, (float) $totals['sums']['qty_total'], 0.001, 'The sum must cover the whole set, not the page');
    }

    public function testSumOnlyAggregatesRowsMatchingTheFilters(): void
    {
        $this->seedItems(); // qty: 1, 2, 3

        $totals = DBGet::Get([
            'table'   => 'items',
            'filters' => [['qty', '1', '>']],
            'joins'   => [],
            'sums'    => ['qty_total' => 'qty'],
        ], 'sum');

        $this->assertSame(2, (int) $totals['results'], 'Only rows above 1 must be counted');
        $this->assertEqualsWithDelta(5.0, (float) $totals['sums']['qty_total'], 0.001, 'Only rows above 1 must be summed');
    }

    public function testSumOverAnEmptyResultSetReturnsZeroCountAndNullSums(): void
    {
        $this->seedItems();

        $totals = DBGet::Get([
            'table'   => 'items',
            'filters' => [['name', 'missing-contract', '=']],
            'joins'   => [],
            'sums'    => ['qty_total' => 'qty'],
        ], 'sum');

        $this->assertSame(0, (int) $totals['results'], 'An empty set must count zero rows');
        $this->assertNull($totals['sums']['qty_total'], 'SUM over no rows is NULL; the middleware maps it to 0.00');
    }

    public function testGroupedSumReturnsOneRowPerGroupWithItsGroupColumn(): void
    {
        $this->seedItems(); // one: 1+2, two: 3

        $groups = DBGet::Get([
            'table'    => 'items',
            'filters'  => [],
            'joins'    => [],
            'sums'     => ['qty_total' => 'qty'],
            'group_by' => ['category'],
        ], 'sum');

        $this->assertCount(2, $groups, 'There must be one row per group');

        // ORDER BY the first group expression keeps the output deterministic.
        $this->assertSame('one', $groups[0]['group']['category']);
        $this->assertSame(2, (int) $groups[0]['results'], 'The group count must be the rows of that group');
        $this->assertEqualsWithDelta(3.0, (float) $groups[0]['sums']['qty_total'], 0.001);

        $this->assertSame('two', $groups[1]['group']['category']);
        $this->assertSame(1, (int) $groups[1]['results']);
        $this->assertEqualsWithDelta(3.0, (float) $groups[1]['sums']['qty_total'], 0.001);
    }

    public function testGroupedSumOnAJoinedColumnAggregatesPerGroup(): void
    {
        $this->seedItems();
        $this->pdo->exec("INSERT INTO categories (name) VALUES ('one'), ('two'), ('three')");
        $this->pdo->exec("INSERT INTO items (name, category, qty) VALUES ('delta', 'three', 4)");

        $groups = DBGet::Get([
            'table'    => 'items',
            'filters'  => [],
            'joins'    => [
                [
                    'table' => 'categories',
                    'match' => ['items.category', 'categories.name'],
                ],
            ],
            'sums'     => ['qty_total' => 'qty'],
            'group_by' => ['categories.name'],
        ], 'sum');

        $this->assertCount(3, $groups, 'The joined column must produce one row per group');
        // Alphabetical order by the group column: one, three, two.
        $this->assertSame('one', $groups[0]['group']['categories.name']);
        $this->assertEqualsWithDelta(3.0, (float) $groups[0]['sums']['qty_total'], 0.001);
        $this->assertSame('three', $groups[1]['group']['categories.name']);
        $this->assertEqualsWithDelta(4.0, (float) $groups[1]['sums']['qty_total'], 0.001);
        $this->assertSame('two', $groups[2]['group']['categories.name']);
        $this->assertEqualsWithDelta(3.0, (float) $groups[2]['sums']['qty_total'], 0.001);
    }
}
