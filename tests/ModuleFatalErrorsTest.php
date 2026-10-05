<?php

use PHPUnit\Framework\TestCase;

require_once CORE_PATH . 'helpers/custom_exceptions.php';
require_once CORE_PATH . 'bootstrap/midelware.php';
require_once CORE_PATH . 'modules/roles/controller.php';
require_once CORE_PATH . 'modules/config/controller.php';

/**
 * Guards that the built-in roles and config modules initialize and expose
 * their CRUD methods without raising unhandled PHP errors.
 */
final class ModuleFatalErrorsTest extends TestCase
{
    public function testRolesModuleConstructsFromRequestContext(): void
    {
        $_GET['id'] = null;

        $roles = new Roles();

        $this->assertInstanceOf(\App\Model\BaseModel::class, $roles);
    }

    public function testConfigModuleUsesCrudTraitAndExposesDestroy(): void
    {
        $this->assertArrayHasKey(\App\Model\Crud::class, class_uses(AppConfig::class));
        $this->assertTrue(method_exists(AppConfig::class, 'destroy'));
        $this->assertTrue(is_callable([new AppConfig(), 'destroy']));
    }
}
