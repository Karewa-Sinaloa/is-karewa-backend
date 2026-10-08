<?php

use PHPUnit\Framework\TestCase;

require_once CORE_PATH . 'helpers/custom_exceptions.php';
require_once CORE_PATH . 'bootstrap/midelware.php';
require_once CORE_PATH . 'modules/config/controller.php';

/**
 * Guards that the config module only maps fields to real columns of the
 * config table (id, name, slug, value, is_private, edit_roles): it must expose
 * `value`, the visibility flag and the per-row edit list, and must not
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

    public function testIsPrivateFieldExistsAndDefaultsToPrivate(): void
    {
        $fields = self::defaults('moduleFields');

        $this->assertArrayHasKey('is_private', $fields);
        $this->assertSame('is_private', $fields['is_private']['field']);
        $this->assertSame(1, $fields['is_private']['default']);
        $this->assertTrue($fields['is_private']['listed']);
        $this->assertTrue($fields['is_private']['filter']);
        $this->assertTrue($fields['is_private']['saved']);
        $this->assertTrue($fields['is_private']['zero_is_value']);
    }

    public function testVisibilityDeclarationPointsToTheIsPrivateColumn(): void
    {
        $visibility = self::defaults('get_params')['visibility'] ?? null;

        $this->assertSame(['column' => 'is_private', 'roles' => [1, 2, 3]], $visibility);
    }

    public function testEditRolesIsReadableButNotWritable(): void
    {
        $fields = self::defaults('moduleFields');

        $this->assertArrayHasKey('edit_roles', $fields);
        $this->assertSame('edit_roles', $fields['edit_roles']['field']);
        $this->assertTrue($fields['edit_roles']['listed'], 'The list must be readable');
        $this->assertTrue($fields['edit_roles']['filter'], 'The list must be filterable');
        $this->assertFalse($fields['edit_roles']['saved'], 'The list is configured in the database, not through the API');
    }

    public function testEditRolesDeclarationPointsToItsColumn(): void
    {
        $declaration = self::defaults('get_params')['edit_roles'] ?? null;

        $this->assertSame(['column' => 'edit_roles', 'always' => [1]], $declaration);
    }

    public function testShowMethodDoesNotRequireAuthentication(): void
    {
        $source = (string) file_get_contents(CORE_PATH . 'modules/config/index.php');

        $this->assertMatchesRegularExpression(
            "/'show'\\s*=>\\s*\\[\\s*false\\s*,\\s*NULL\\s*\\]/",
            $source,
            'Reading an entry must be possible without a token so public entries are readable by everyone'
        );
    }
}
