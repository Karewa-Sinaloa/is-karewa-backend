<?php

require_once __DIR__ . '/Support/OrTestCase.php';
require_once CORE_PATH . 'bootstrap/midelware.php';
require_once CORE_PATH . 'modules/hcaptcha/controller.php';

/**
 * Guards the administrator-only hCaptcha bypass secret: the route is registered,
 * the operation is gated to role 1, the secret is stored as an irreversible hash
 * (not plaintext), and rotation invalidates the previous secret.
 */
final class HcaptchaBypassModuleTest extends OrTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo->exec(
            'CREATE TABLE users ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' hcaptcha_bypass TEXT DEFAULT NULL'
            . ')'
        );
        $this->pdo->exec('INSERT INTO users (id, hcaptcha_bypass) VALUES (1, NULL)');

        global $_payload;
        $_payload = new stdClass();

        if (!defined('USER_ID')) {
            define('USER_ID', 1);
        }
    }

    private function storedHash(): string
    {
        return (string) $this->pdo
            ->query('SELECT hcaptcha_bypass FROM users WHERE id = 1')
            ->fetchColumn();
    }

    public function testRouteIsRegistered(): void
    {
        $routes = file_get_contents(CORE_PATH . 'bootstrap/routes.php');

        $this->assertStringContainsString("'hcaptcha' => 'hcaptcha'", $routes);
    }

    public function testIndexGatesTheOperationToAdministrators(): void
    {
        $source     = file_get_contents(CORE_PATH . 'modules/hcaptcha/index.php');
        $normalized = preg_replace('/\s+/', ' ', $source);

        $this->assertStringContainsString("'store' => [true, [1]]", $normalized);
    }

    public function testGenerateStoresAnIrreversibleHash(): void
    {
        $controller = new HCaptchaBypass();
        $secret     = $controller->GenerateSecret();

        $stored = $this->storedHash();

        $this->assertNotSame($secret, $stored);
        $this->assertTrue(password_verify($secret, $stored));
    }

    public function testRotationInvalidatesThePreviousSecret(): void
    {
        $controller = new HCaptchaBypass();
        $first      = $controller->GenerateSecret();
        $second     = $controller->GenerateSecret();

        $stored = $this->storedHash();

        $this->assertTrue(password_verify($second, $stored));
        $this->assertFalse(password_verify($first, $stored));
    }
}
