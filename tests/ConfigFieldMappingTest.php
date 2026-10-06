<?php

use PHPUnit\Framework\TestCase;

require_once CORE_PATH . 'helpers/custom_exceptions.php';
require_once CORE_PATH . 'bootstrap/midelware.php';
require_once CORE_PATH . 'modules/config/controller.php';

/**
 * Guards that the config module only maps fields to real columns of the
 * config table (id, name, slug, value): it must expose `value`, and must not
 * reference the non-existent `data` or `public` columns in its fields, its
 * searchable columns, or its validation rules.
 */
final class ConfigFieldMappingTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function defaults(string $property): array
    {
        $properties = (new ReflectionClass(AppConfig::class))->getDefaultProperties();
        return $properties[$property];
    }

    public function testValueFieldMapsToTheValueColumn(): void
    {
        $fields = self::defaults('moduleFields');

        $this->assertArrayHasKey('value', $fields);
        $this->assertSame('value', $fields['value']['field']);
    }

    public function testFieldsDoNotReferenceDataOrPublic(): void
    {
        $fields = self::defaults('moduleFields');

        foreach (['data', 'public'] as $removed) {
            $this->assertArrayNotHasKey($removed, $fields, "{$removed} should not be declared");
        }
    }

    public function testSearchOnlyUsesExistingColumns(): void
    {
        $search = self::defaults('get_params')['search'] ?? [];

        $this->assertSame(['name', 'slug'], $search);
        foreach (['data', 'public'] as $removed) {
            $this->assertNotContains($removed, $search, "{$removed} is not a column");
        }
    }

    public function testRulesDoNotValidateDataOrPublic(): void
    {
        $rules = self::defaults('rules');

        foreach (['data', 'public'] as $removed) {
            $this->assertArrayNotHasKey($removed, $rules, "{$removed} has no column to validate");
        }
        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('slug', $rules);
    }
}
