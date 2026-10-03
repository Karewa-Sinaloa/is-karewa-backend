<?php

use PHPUnit\Framework\TestCase;

require_once CORE_PATH . 'helpers/custom_exceptions.php';
require_once CORE_PATH . 'bootstrap/midelware.php';
require_once CORE_PATH . 'modules/roles/controller.php';
require_once CORE_PATH . 'modules/users/controller.php';

/**
 * Guards that the roles and users modules reference the canonical base table
 * names (`roles`, `users_status`) rather than the non-existent `user_roles` /
 * `user_status` names, so they resolve correctly under any configured prefix.
 *
 * Default class properties are inspected instead of instances so the check does
 * not depend on module construction (the roles constructor type error is tracked
 * separately by fix-module-fatal-errors).
 */
final class RoleTableNamingTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function defaultProperty(string $class, string $property): array
    {
        $defaults = (new ReflectionClass($class))->getDefaultProperties();
        return $defaults[$property];
    }

    public function testRolesModuleUsesCanonicalRoleTable(): void
    {
        $getParams = self::defaultProperty(Roles::class, 'get_params');

        $this->assertSame('roles', $getParams['table']);
    }

    public function testUsersModuleJoinsCanonicalTables(): void
    {
        $getParams = self::defaultProperty(Users::class, 'get_params');
        $joins = $getParams['joins'];

        $tables = array_column($joins, 'table');
        $this->assertContains('roles r', $tables);
        $this->assertContains('users_status s', $tables);
    }

    public function testUsersValidationUsesCanonicalTables(): void
    {
        $rules = self::defaultProperty(Users::class, 'rules');

        $this->assertStringContainsString('exist:roles:id', $rules['role_id']);
        $this->assertStringContainsString('exist:users_status:id', $rules['status_id']);
    }

    public function testNoLegacyTableNamesRemain(): void
    {
        $rolesParams = self::defaultProperty(Roles::class, 'get_params');
        $usersParams = self::defaultProperty(Users::class, 'get_params');
        $usersRules  = self::defaultProperty(Users::class, 'rules');

        $haystack = json_encode([$rolesParams, $usersParams, $usersRules]);

        $this->assertStringNotContainsString('user_roles', $haystack);
        $this->assertStringNotContainsString('user_status', $haystack);
    }
}
