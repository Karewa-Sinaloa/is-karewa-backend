<?php

use PHPUnit\Framework\TestCase;

/**
 * Guards the API response envelope produced by ApiResponse::Set(): a known code
 * renders its catalog entry, an unknown code falls back to the internal-error
 * envelope instead of fatalling, and extra data never replaces a reserved field.
 *
 * ApiResponse::Set() terminates the process with die(), so every case runs in a
 * subprocess with the constants and the error_logs() stub the helper needs.
 */
final class ApiResponseTest extends TestCase
{
    /**
     * Run ApiResponse::Set() in a subprocess and capture the body, the emitted
     * HTTP status code, the raw output and the process exit code.
     *
     * @return array{body: string, http: ?int, raw: string, exit: int}
     */
    private function invoke(string $code, ?array $data = null, ?array $options = null, ?array $allowedRoles = null, bool $authenticated = false): array
    {
        $script = tempnam(sys_get_temp_dir(), 'apiresp_') . '.php';
        file_put_contents($script, "<?php\n"
            . "define('CORE_PATH', " . var_export(CORE_PATH, true) . ");\n"
            . "define('IDENTIFIER_UID', 'test-session-id');\n"
            . "define('ERROR_LOG_FILE', " . var_export(sys_get_temp_dir() . '/karewa-error.log', true) . ");\n"
            . "define('DEBUG_LOG_FILE', " . var_export(sys_get_temp_dir() . '/karewa-debug.log', true) . ");\n"
            . "function error_logs(array \$data, string \$file = DEBUG_LOG_FILE): void {}\n"
            . "require " . var_export($this->vendorAutoload(), true) . ";\n"
            . "require CORE_PATH . 'helpers/api_response.php';\n"
            . "register_shutdown_function(function () { echo \"\n<<<HTTP_CODE>>>\" . http_response_code(); });\n"
            . ($authenticated ? "define('AUTHENTICATED', true);\n" : '')
            . ($allowedRoles !== null
                ? "\\App\\Helpers\\ApiResponse::SetAllowedRoles(" . var_export($allowedRoles, true) . ");\n"
                : '')
            . "\\App\\Helpers\\ApiResponse::Set("
            . var_export($code, true) . ', '
            . var_export($data, true) . ', '
            . var_export($options, true) . ");\n");

        $command = escapeshellarg(PHP_BINARY)
            . ' -d display_errors=1 -d log_errors=0 '
            . escapeshellarg($script) . ' 2>&1';

        $lines = [];
        $exitCode = 0;
        exec($command, $lines, $exitCode);
        @unlink($script);

        $raw = implode("\n", $lines);
        $http = null;
        if (preg_match('/<<<HTTP_CODE>>>(\d+)/', $raw, $matches)) {
            $http = (int) $matches[1];
        }
        $body = $raw;
        $marker = strpos($raw, "\n<<<HTTP_CODE>>>");
        if ($marker !== false) {
            $body = substr($raw, 0, $marker);
        }

        return ['body' => $body, 'http' => $http, 'raw' => $raw, 'exit' => $exitCode];
    }

    private function vendorAutoload(): string
    {
        return dirname(dirname(rtrim(CORE_PATH, '/'))) . '/vendor/autoload.php';
    }

    public function testKnownCodeRendersDocumentedEnvelope(): void
    {
        $result = $this->invoke('SUCCESS');

        $this->assertSame(0, $result['exit']);
        $this->assertStringNotContainsString('Fatal error', $result['raw']);

        $json = json_decode($result['body'], true);
        $this->assertIsArray($json);
        $this->assertSame('Success', $json['message']);
        $this->assertSame('SUCCESS', $json['code']);
        $this->assertSame(200, $json['http_code']);
        $this->assertSame('test-session-id', $json['meta']['session_id']);
        $this->assertSame(200, $result['http']);
    }

    public function testUnknownCodeFallsBackToInternalErrorEnvelope(): void
    {
        $result = $this->invoke('THIS_CODE_DOES_NOT_EXIST');

        $this->assertSame(0, $result['exit']);
        $this->assertStringNotContainsString('Fatal error', $result['raw']);
        $this->assertStringNotContainsString('Uncaught', $result['raw']);

        $json = json_decode($result['body'], true);
        $this->assertIsArray($json);
        $this->assertSame('Internal server error', $json['message']);
        $this->assertSame('APP_INTERNAL_SERVER_ERROR', $json['code']);
        $this->assertSame(500, $json['http_code']);
        $this->assertSame('test-session-id', $json['meta']['session_id']);
        $this->assertSame(500, $result['http']);
    }

    public function testExtraDataDoesNotOverrideReservedFields(): void
    {
        $result = $this->invoke('SUCCESS', [
            'code' => 'HACKED',
            'http_code' => 418,
            'message' => 'HACKED',
            'meta' => ['session_id' => 'hacked'],
            'extra' => 'kept',
        ]);

        $this->assertSame(0, $result['exit']);

        $json = json_decode($result['body'], true);
        $this->assertIsArray($json);
        $this->assertSame('Success', $json['message']);
        $this->assertSame('SUCCESS', $json['code']);
        $this->assertSame(200, $json['http_code']);
        $this->assertSame('test-session-id', $json['meta']['session_id']);
        $this->assertSame('kept', $json['extra']);
        $this->assertSame(200, $result['http']);
    }

    public function testAuthenticatedResponseCarriesAllowedRoles(): void
    {
        $roles = ['create' => [1, 2, 3], 'edit' => [1], 'delete' => [1, 2]];
        $result = $this->invoke('SUCCESS', null, null, $roles, true);

        $this->assertSame(0, $result['exit']);
        $this->assertStringNotContainsString('Fatal error', $result['raw']);

        $json = json_decode($result['body'], true);
        $this->assertIsArray($json);
        $this->assertSame('Success', $json['message']);
        $this->assertSame('SUCCESS', $json['code']);
        $this->assertArrayHasKey('allowed_roles', $json);
        $this->assertSame($roles, $json['allowed_roles']);
    }

    public function testAnonymousResponseOmitsAllowedRoles(): void
    {
        $result = $this->invoke('SUCCESS', ['allowed_roles' => ['create' => [9]]]);

        $this->assertSame(0, $result['exit']);

        $json = json_decode($result['body'], true);
        $this->assertIsArray($json);
        $this->assertArrayNotHasKey('allowed_roles', $json);
        $this->assertStringNotContainsString('allowed_roles', $result['body']);
    }

    public function testAllowedRolesCannotBeOverriddenByExtraData(): void
    {
        $roles = ['create' => [1]];
        $result = $this->invoke('SUCCESS', [
            'allowed_roles' => ['create' => [9, 9]],
            'extra' => 'kept',
        ], null, $roles, true);

        $this->assertSame(0, $result['exit']);

        $json = json_decode($result['body'], true);
        $this->assertIsArray($json);
        $this->assertSame($roles, $json['allowed_roles']);
        $this->assertSame('kept', $json['extra']);
    }

    public function testAuthenticatedResponseWithNoPublishedRolesCarriesEmptyObject(): void
    {
        $result = $this->invoke('SUCCESS', null, null, [], true);

        $this->assertSame(0, $result['exit']);
        $this->assertStringContainsString('"allowed_roles":{}', $result['body']);
    }
}
