<?php

use PHPUnit\Framework\TestCase;

/**
 * Exercises the CORS allow-listing and security headers emitted centrally in
 * app/core/config/base.php. Because base.php calls header() and dies on a
 * denied origin, each scenario runs in the CGI SAPI (which exposes the real
 * response headers) against a temporary app/config.yml.
 */
final class CorsHeadersTest extends TestCase
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

    private function writeConfig(string $corsDomains, bool $wildcard, bool $https): void
    {
        $yaml = "timezone: UTC\n"
              . "development: false\n"
              . 'https: ' . ($https ? 'true' : 'false') . "\n"
              . "log:\n  path: ../logs\n  debug: /debug.log\n  error: /error.log\n  paypal: /payments.log\n  frontend: /frontend.log\n"
              . "session:\n  time: 86400\n  prefix: krw_\n"
              . "database:\n  host: localhost\n  user: u\n  password: p\n  database: d\n  port: 3306\n  prefix: ''\n"
              . "jwt:\n  encoding: RS256\n  message: test\n"
              . "access:\n  code_expiration_time: 3600\n"
              . "cart:\n  expiration: 3600\n"
              . "cors:\n  active: true\n  wildcard: " . ($wildcard ? 'true' : 'false') . "\n  domains:\n" . $corsDomains
              . "rate_limit:\n  enabled: false\n  window: 60\n  default_limit: 120\n  sensitive_limit: 10\n  sensitive: []\n  endpoints: {}\n  trusted_proxy: ''\n  proxy_header: ''\n"
              . "security:\n  headers:\n    nosniff: true\n    frame_options: DENY\n    referrer_policy: no-referrer\n    hsts: true\n    hsts_max_age: 31536000\n";

        file_put_contents(self::$configPath, $yaml);
    }

    /**
     * Run base.php through the CGI SAPI and return the emitted headers.
     *
     * @return array<string, string>
     */
    private static ?string $cgiBinary = null;

    private function cgiBinary(): ?string
    {
        if (self::$cgiBinary !== null) {
            return self::$cgiBinary ?: null;
        }
        $candidates = [PHP_BINDIR . '/php-cgi', '/usr/bin/php-cgi', '/usr/local/bin/php-cgi'];
        foreach ($candidates as $candidate) {
            if (is_executable($candidate)) {
                self::$cgiBinary = $candidate;
                return $candidate;
            }
        }
        self::$cgiBinary = '';
        return null;
    }

    /**
     * Run base.php through the CGI SAPI and return the emitted headers.
     * CGI is required because the CLI SAPI does not expose response headers.
     *
     * @return array<string, string>
     */
    private function runBase(string $origin): array
    {
        $cgi = $this->cgiBinary();
        if ($cgi === null) {
            $this->markTestSkipped('php-cgi is required to inspect response headers');
        }

        // base.php expects the Composer autoloader and DOCUMENT_ROOT to be
        // present, so run it through a small generated wrapper. Running it via
        // the CGI SAPI is required because the CLI SAPI does not expose headers.
        $wrapper = tempnam(sys_get_temp_dir(), 'cors_') . '.php';
        file_put_contents($wrapper, "<?php\n"
            . "\$_SERVER['DOCUMENT_ROOT'] = " . var_export($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html', true) . ";\n"
            . "require " . var_export(dirname(CORE_PATH) . '/../vendor/autoload.php', true) . ";\n"
            . "require " . var_export(ROOT_PATH . 'core/config/base.php', true) . ";\n");

        $env = [
            'SCRIPT_FILENAME' => $wrapper,
            'REQUEST_METHOD'  => 'GET',
            'REDIRECT_STATUS' => '1',
            'REMOTE_ADDR'     => '198.51.100.1',
            'DOCUMENT_ROOT'   => $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html',
        ];
        if ($origin !== '') {
            $env['HTTP_ORIGIN'] = $origin;
        }

        // php-cgi requires the target script as an argument; REDIRECT_STATUS
        // disables the CGI security redirect guard. stderr is not merged: PHP
        // warnings would otherwise precede the header block.
        $prefix = '';
        foreach ($env as $k => $v) {
            $prefix .= $k . '=' . escapeshellarg($v) . ' ';
        }

        $output = shell_exec($prefix . escapeshellarg($cgi) . ' ' . escapeshellarg($wrapper) . ' 2>/dev/null');
        @unlink($wrapper);

        // Headers are everything before the first blank line (start of body).
        $block = preg_split("/\r?\n\r?\n/", (string) $output, 2)[0];

        $headers = [];
        foreach (explode("\n", $block) as $line) {
            if (preg_match('/^([A-Za-z0-9-]+):\s*(.*)$/', rtrim($line, "\r"), $m)) {
                $headers[strtolower($m[1])] = trim($m[2]);
            }
        }
        return $headers;
    }

    public function testAllowedOriginIsGrantedWithCredentials(): void
    {
        $this->writeConfig("    - https://allowed.example\n", false, true);
        $headers = $this->runBase('https://allowed.example');

        $this->assertSame('https://allowed.example', $headers['access-control-allow-origin'] ?? null);
        $this->assertSame('true', $headers['access-control-allow-credentials'] ?? null);
    }

    public function testDisallowedOriginDoesNotGrantAccess(): void
    {
        $this->writeConfig("    - https://allowed.example\n", false, true);
        $headers = $this->runBase('https://evil.example');

        $this->assertArrayNotHasKey('access-control-allow-origin', $headers);
    }

    public function testWildcardNeverCombinesWithCredentials(): void
    {
        $this->writeConfig("    - https://allowed.example\n", true, false);
        $headers = $this->runBase('https://evil.example');

        $this->assertSame('*', $headers['access-control-allow-origin'] ?? null);
        $this->assertArrayNotHasKey('access-control-allow-credentials', $headers);
    }

    public function testSecurityHeadersAreEmitted(): void
    {
        $this->writeConfig("    - https://allowed.example\n", false, true);
        $headers = $this->runBase('https://allowed.example');

        $this->assertSame('nosniff', $headers['x-content-type-options'] ?? null);
        $this->assertSame('DENY', $headers['x-frame-options'] ?? null);
        $this->assertSame('no-referrer', $headers['referrer-policy'] ?? null);
    }

    public function testHstsEmittedWhenHttps(): void
    {
        $this->writeConfig("    - https://allowed.example\n", false, true);
        $headers = $this->runBase('https://allowed.example');

        $this->assertStringContainsString('max-age=', $headers['strict-transport-security'] ?? '');
    }
}
