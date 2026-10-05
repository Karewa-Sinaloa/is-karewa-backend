<?php

use PHPUnit\Framework\TestCase;

require_once CORE_PATH . 'helpers/custom_exceptions.php';
require_once CORE_PATH . 'bootstrap/midelware.php';
require_once CORE_PATH . 'modules/access/local_login.php';

/**
 * Guards the login bypass decision: only an enabled administrator (role_id = 1)
 * whose stored hash matches the presented secret may skip hCaptcha. Any other
 * case (wrong secret, absent secret, non-admin, no stored secret, unknown user)
 * must not be granted.
 */
final class HcaptchaLoginBypassTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function admin(array $overrides = []): array
    {
        return array_merge([
            'id'              => 1,
            'role_id'         => 1,
            'hcaptcha_bypass' => password_hash('correct-secret', PASSWORD_DEFAULT),
        ], $overrides);
    }

    public function testAdminWithMatchingSecretIsGranted(): void
    {
        $this->assertTrue(AppAccess::IsBypassGranted(self::admin(), 'correct-secret'));
    }

    public function testAdminWithWrongSecretIsNotGranted(): void
    {
        $this->assertFalse(AppAccess::IsBypassGranted(self::admin(), 'wrong-secret'));
    }

    public function testAbsentSecretIsNotGranted(): void
    {
        $this->assertFalse(AppAccess::IsBypassGranted(self::admin(), null));
        $this->assertFalse(AppAccess::IsBypassGranted(self::admin(), ''));
    }

    public function testNonAdminIsNotGrantedEvenWithMatchingSecret(): void
    {
        $user = self::admin(['role_id' => 2]);

        $this->assertFalse(AppAccess::IsBypassGranted($user, 'correct-secret'));
    }

    public function testAdminWithoutStoredSecretIsNotGranted(): void
    {
        $user = self::admin(['hcaptcha_bypass' => null]);

        $this->assertFalse(AppAccess::IsBypassGranted($user, 'correct-secret'));
    }

    public function testUnknownUserIsNotGranted(): void
    {
        $this->assertFalse(AppAccess::IsBypassGranted(null, 'correct-secret'));
    }
}
