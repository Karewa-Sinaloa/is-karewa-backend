<?php

use PHPUnit\Framework\TestCase;

require_once CORE_PATH . 'helpers/custom_exceptions.php';
require_once CORE_PATH . 'bootstrap/midelware.php';
require_once CORE_PATH . 'modules/access/local_login.php';

/**
 * Guards that the access module (login, recovery, reset) only references real
 * users columns: it must use `recovery_date` (not `recovery_datetime`) and must
 * not write a non-existent `email_verified` column.
 *
 * Default class properties are inspected so the check does not depend on module
 * construction.
 */
final class AccessFieldMappingTest extends TestCase
{
    private const MODULE_FILE = CORE_PATH . 'modules/access/local_login.php';

    /** @return array<string, mixed> */
    private static function moduleFields(): array
    {
        $defaults = (new ReflectionClass(AppAccess::class))->getDefaultProperties();
        return $defaults['moduleFields'];
    }

    public function testRecoveryDateMapsToTheRealColumn(): void
    {
        $fields = self::moduleFields();

        $this->assertArrayHasKey('recovery_date', $fields);
        $this->assertSame('recovery_date', $fields['recovery_date']['field']);
    }

    public function testModuleDoesNotReferenceRecoveryDatetime(): void
    {
        $source = file_get_contents(self::MODULE_FILE);

        $this->assertStringNotContainsString('recovery_datetime', $source);
    }

    public function testModuleDoesNotWriteEmailVerified(): void
    {
        $source = file_get_contents(self::MODULE_FILE);

        $this->assertStringNotContainsString('email_verified', $source);
    }
}
