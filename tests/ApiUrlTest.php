<?php

use PHPUnit\Framework\TestCase;

/**
 * Verifies that base.php defines API_URL as a usable absolute URL string rather
 * than the raw `api` configuration object.
 *
 * base.php reads the real app/config.yml and defines many constants, so each
 * case runs in a subprocess against a temporary config.
 */
final class ApiUrlTest extends TestCase
{
    private static string $configPath;
    private static ?string $configBackup = null;

    public static function setUpBeforeClass(): void
    {
        self::$configPath = ROOT_PATH . 'config.yml';
        if (is_file(self::$configPath)) {
            self::$configBackup = file_get_contents(self::$configPath);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$configBackup !== null) {
            file_put_contents(self::$configPath, self::$configBackup);
        } elseif (is_file(self::$configPath)) {
            unlink(self::$configPath);
        }
    }

    /**
     * Run base.php in a subprocess with a controlled `api` node and return the
     * defined API_URL, or null if it could not be defined.
     */
    private function resolveApiUrl(string $apiYaml): ?string
    {
        $yaml = "timezone: UTC\n"
              . "domain: example.test\n"
              . $apiYaml
              . "development: false\n"
              . "log:\n  path: ../logs\n  debug: /debug.log\n  error: /error.log\n  paypal: /payments.log\n  frontend: /frontend.log\n"
              . "session:\n  time: 86400\n  prefix: krw_\n"
              . "database:\n  host: localhost\n  user: u\n  password: p\n  database: d\n  port: 3306\n  prefix: ''\n"
              . "cms: https://cms.example\n"
              . "jwt:\n  encoding: RS256\n  message: test\n"
              . "access:\n  code_expiration_time: 3600\n"
              . "cart:\n  expiration: 3600\n"
              . "statics:\n  url: /s/\n  path: s/\n  images: s/i/\n  attachments: s/a/\n"
              . "mailings:\n  uuid: u\n  url: https://m.example\n  hash: h\n"
              . "hash: h\n"
              . "https: true\n";

        file_put_contents(self::$configPath, $yaml);

        $script = tempnam(sys_get_temp_dir(), 'apiurl_') . '.php';
        file_put_contents($script, "<?php\n"
            . "\$_SERVER['DOCUMENT_ROOT'] = '/var/www/html';\n"
            . "require " . var_export(dirname(CORE_PATH) . '/../vendor/autoload.php', true) . ";\n"
            . "require " . var_export(ROOT_PATH . 'core/config/base.php', true) . ";\n"
            . "echo API_URL;\n");

        $output = shell_exec(
            'SCRIPT_FILENAME=' . escapeshellarg($script)
            . ' REQUEST_METHOD=GET REDIRECT_STATUS=1 REMOTE_ADDR=127.0.0.1'
            . ' DOCUMENT_ROOT=/var/www/html '
            . escapeshellarg(PHP_BINARY) . ' -d display_errors=0 ' . escapeshellarg($script) . ' 2>/dev/null'
        );
        @unlink($script);

        $output = trim((string) $output);
        return $output === '' ? null : $output;
    }

    public function testApiUrlIsAStringBuiltFromTheApiMap(): void
    {
        $url = $this->resolveApiUrl("api:\n  url: kapi.example.test/api/v5/\n  https: true\n");

        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://', $url);
        $this->assertStringContainsString('kapi.example.test/api/v5/', $url);
    }

    public function testApiUrlUsesHttpWhenHttpsIsFalse(): void
    {
        $url = $this->resolveApiUrl("api:\n  url: kapi.example.test/api/v5/\n  https: false\n");

        $this->assertNotNull($url);
        $this->assertStringStartsWith('http://', $url);
        $this->assertStringNotContainsString('https://', $url);
    }

    public function testApiUrlDoesNotDoublePrefixAnExistingScheme(): void
    {
        $url = $this->resolveApiUrl("api:\n  url: https://kapi.example.test/api/v5/\n  https: true\n");

        $this->assertNotNull($url);
        $this->assertSame('https://kapi.example.test/api/v5/', $url);
    }
}
