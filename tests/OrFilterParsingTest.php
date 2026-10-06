<?php

use PHPUnit\Framework\TestCase;
use App\Model\BaseModel;

require_once CORE_PATH . 'helpers/custom_exceptions.php';
require_once CORE_PATH . 'bootstrap/midelware.php';

/**
 * Exposes BaseModel's private filter parser so the operator/value split can be
 * asserted directly, including values that themselves contain colons.
 */
final class OrFilterParsingProbe extends BaseModel
{
    public function withFields(array $fields): self
    {
        $this->availableFields = $fields;
        return $this;
    }

    /** @return array<string, array> */
    public function filters(array $searchFields = []): array
    {
        $method = new ReflectionMethod(BaseModel::class, 'getParamsFilters');
        return $method->invoke($this, $searchFields);
    }
}

final class OrFilterParsingTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    /** @return array<int, string> */
    private function filterFor(string $raw): array
    {
        $_GET['name'] = $raw;
        $probe = (new OrFilterParsingProbe())->withFields([
            'name' => ['field' => 'i.name', 'filter' => true],
        ]);
        return $probe->filters()['name'];
    }

    public function testPlainValueDefaultsToEquality(): void
    {
        $this->assertSame(['i.name', '42', '='], $this->filterFor('42'));
    }

    public function testOperatorPrefixIsApplied(): void
    {
        $this->assertSame(['i.name', '42', '='], $this->filterFor('eq:42'));
    }

    public function testValueWithColonKeepsTheRemainderIntact(): void
    {
        $this->assertSame(['i.name', '10:30', 'LIKE'], $this->filterFor('lk:10:30'));
    }
}
