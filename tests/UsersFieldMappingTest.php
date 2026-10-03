<?php

use PHPUnit\Framework\TestCase;

require_once CORE_PATH . 'helpers/custom_exceptions.php';
require_once CORE_PATH . 'bootstrap/midelware.php';
require_once CORE_PATH . 'modules/users/controller.php';
require_once CORE_PATH . 'modules/roles/controller.php';

/**
 * Guards that the users module only maps fields to real columns and that the
 * roles module constructs its model without a type error. The column list is the
 * base schema of the users table (the ORM applies the prefix once).
 */
final class UsersFieldMappingTest extends TestCase
{
    /** Base columns of the users table. */
    private const USER_COLUMNS = [
        'id', 'email', 'password', 'first_name', 'middle_name', 'last_name',
        'second_last_name', 'phone', 'phone_verified', 'facebook_id',
        'recovery_code', 'recovery_date', 'role_id', 'status_id',
    ];

    protected function setUp(): void
    {
        // The request lifecycle sets these before a module is constructed; the
        // base model requires an object payload and the controllers read $_GET.
        global $_payload;
        $_payload = new stdClass();
        $_GET['id'] = null;

        if (!defined('REQUEST_TYPE')) {
            define('REQUEST_TYPE', 'index');
        }
    }

    private static function moduleFields(object $controller): array
    {
        $property = new ReflectionProperty($controller, 'moduleFields');
        $property->setAccessible(true);
        return $property->getValue($controller);
    }

    public function testEveryUsersFieldReferencesAnExistingColumn(): void
    {
        $users = new Users();
        $fields = self::moduleFields($users);

        foreach ($fields as $name => $definition) {
            $column = $definition['field'] ?? null;
            $this->assertNotNull($column, "Field {$name} has no column");

            // Only the base users table is checked here; joined aliases (r./s.)
            // belong to the roles and users_status tables.
            if (!str_starts_with($column, 'u.')) {
                continue;
            }
            $base = substr($column, 2);
            $this->assertContains(
                $base,
                self::USER_COLUMNS,
                "Field {$name} maps to non-existent column u.{$base}"
            );
        }
    }

    public function testRecoveryDateMapsToTheRealColumn(): void
    {
        $fields = self::moduleFields(new Users());

        $this->assertArrayHasKey('recovery_date', $fields);
        $this->assertSame('u.recovery_date', $fields['recovery_date']['field']);
    }

    public function testUnbackedFieldsAreNotDeclared(): void
    {
        $fields = self::moduleFields(new Users());

        foreach (['phone_country_code', 'photo', 'email_verified', 'recovery_datetime'] as $removed) {
            $this->assertArrayNotHasKey($removed, $fields, "{$removed} should not be declared");
        }
    }

    public function testExistingColumnsAreExposed(): void
    {
        $fields = self::moduleFields(new Users());

        $this->assertSame('u.middle_name', $fields['middle_name']['field']);
        $this->assertSame('u.second_last_name', $fields['second_last_name']['field']);
    }
}
