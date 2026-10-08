<?php

use PHPUnit\Framework\TestCase;

require_once CORE_PATH . 'config/mail_env.php';

/**
 * Guards the stored SMTP row: its keys are mapped to the helper's settings,
 * authentication is derived from the stored credentials, a missing or
 * malformed row leaves the file values in place while recording the problem,
 * and the resolved settings follow environment > row > file.
 */
final class MailSmtpRowTest extends TestCase
{
    private mixed $originalConfig = null;
    private mixed $originalApiConfig = null;

    protected function setUp(): void
    {
        $this->originalConfig = $GLOBALS['_config'] ?? null;
        $this->originalApiConfig = $GLOBALS['_apiConfig'] ?? null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['_config'] = $this->originalConfig;
        $GLOBALS['_apiConfig'] = $this->originalApiConfig;
    }

    private function fileLayer(array $overrides = []): object
    {
        return (object) array_merge([
            'debug'      => 1,
            'host'       => 'smtp.sendgrid.net',
            'port'       => 465,
            'security'   => 'ssl',
            'user'       => 'apikey',
            'password'   => 'file-secret',
            'smtp_auth'  => true,
            'from_email' => 'file@example.com',
            'from_name'  => 'File Sender',
        ], $overrides);
    }

    private function rowValue(array $overrides = []): string
    {
        return json_encode(array_merge([
            'host'     => 'smtp.zoho.com',
            'port'     => 465,
            'user'     => 'row-user',
            'pass'     => 'row-secret',
            'from'     => 'row@example.com',
            'security' => 'ssl',
        ], $overrides));
    }

    private function env(array $vars): callable
    {
        return static fn(string $name) => $vars[$name] ?? null;
    }

    public function testRowMapsItsKeysOntoTheSettings(): void
    {
        $mailing = apply_smtp_config_row($this->fileLayer(), $this->rowValue());

        $this->assertSame('smtp.zoho.com', $mailing->host);
        $this->assertSame(465, $mailing->port);
        $this->assertSame('ssl', $mailing->security);
        $this->assertSame('row-user', $mailing->user);
        $this->assertSame('row-secret', $mailing->password);
        $this->assertSame('row@example.com', $mailing->from_email);
        $this->assertTrue($mailing->smtp_auth);
        $this->assertSame(0, $mailing->debug);
        $this->assertSame('', $mailing->from_name);
    }

    public function testRowWithoutCredentialsDisablesAuthentication(): void
    {
        foreach ([['user' => '', 'pass' => 'row-secret'], ['user' => 'row-user', 'pass' => '']] as $missing) {
            $mailing = apply_smtp_config_row($this->fileLayer(), $this->rowValue($missing));

            $this->assertFalse($mailing->smtp_auth, 'An empty credential must disable authentication');
        }
    }

    public function testAbsentRowLeavesTheFileValuesUntouched(): void
    {
        $messages = [];
        $mailing = $this->fileLayer();

        $result = apply_smtp_config_row($mailing, null, function (string $message) use (&$messages) {
            $messages[] = $message;
        });

        $this->assertSame($mailing, $result);
        $this->assertSame('smtp.sendgrid.net', $result->host);
        $this->assertSame('File Sender', $result->from_name);
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('smtp_config', $messages[0]);
    }

    public function testMalformedValueLeavesTheFileValuesUntouched(): void
    {
        $messages = [];
        $mailing = $this->fileLayer();

        $result = apply_smtp_config_row($mailing, '{not json', function (string $message) use (&$messages) {
            $messages[] = $message;
        });

        $this->assertSame($mailing, $result);
        $this->assertSame('smtp.sendgrid.net', $result->host);
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('not a JSON object', $messages[0]);
    }

    public function testStoredRowWinsOverTheFile(): void
    {
        $GLOBALS['_config'] = (object) ['mailing' => $this->fileLayer()];
        $GLOBALS['_apiConfig'] = (object) ['smtp_config' => $this->rowValue()];

        $mailing = resolve_mailing_settings($this->env([]), $this->failOnLog());

        $this->assertSame('smtp.zoho.com', $mailing->host);
        $this->assertSame('row-user', $mailing->user);
        $this->assertSame('row-secret', $mailing->password);
        $this->assertSame('row@example.com', $mailing->from_email);
        $this->assertSame('', $mailing->from_name);
        $this->assertSame(0, $mailing->debug);
    }

    public function testEnvironmentWinsOverTheStoredRow(): void
    {
        $GLOBALS['_config'] = (object) ['mailing' => $this->fileLayer()];
        $GLOBALS['_apiConfig'] = (object) ['smtp_config' => $this->rowValue()];

        $mailing = resolve_mailing_settings($this->env([
            'MAIL_HOST'      => 'mailpit',
            'MAIL_PORT'      => '1025',
            'MAIL_USER'      => 'env-user',
            'MAIL_PASSWORD'  => 'env-secret',
            'MAIL_AUTH'      => 'false',
            'MAIL_FROM_NAME' => 'Env Sender',
        ]), $this->failOnLog());

        $this->assertSame('mailpit', $mailing->host);
        $this->assertSame(1025, $mailing->port);
        $this->assertSame('env-user', $mailing->user);
        $this->assertSame('env-secret', $mailing->password);
        $this->assertFalse($mailing->smtp_auth);
        $this->assertSame('Env Sender', $mailing->from_name);
        $this->assertSame('row@example.com', $mailing->from_email);
    }

    public function testMissingRowFallsBackToTheFileAndRecordsIt(): void
    {
        $GLOBALS['_config'] = (object) ['mailing' => $this->fileLayer()];
        $GLOBALS['_apiConfig'] = null;

        $messages = [];
        $mailing = resolve_mailing_settings($this->env([]), function (string $message) use (&$messages) {
            $messages[] = $message;
        });

        $this->assertSame('smtp.sendgrid.net', $mailing->host);
        $this->assertSame('file-secret', $mailing->password);
        $this->assertSame('File Sender', $mailing->from_name);
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('smtp_config', $messages[0]);
    }

    public function testMalformedRowFallsBackToTheFileAndRecordsIt(): void
    {
        $GLOBALS['_config'] = (object) ['mailing' => $this->fileLayer()];
        $GLOBALS['_apiConfig'] = (object) ['smtp_config' => 'plain text'];

        $messages = [];
        $mailing = resolve_mailing_settings($this->env([]), function (string $message) use (&$messages) {
            $messages[] = $message;
        });

        $this->assertSame('smtp.sendgrid.net', $mailing->host);
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('not a JSON object', $messages[0]);
    }

    public function testResolutionLeavesTheFileLayerUntouched(): void
    {
        $GLOBALS['_config'] = (object) ['mailing' => $this->fileLayer()];
        $GLOBALS['_apiConfig'] = (object) ['smtp_config' => $this->rowValue()];

        resolve_mailing_settings($this->env([]), $this->failOnLog());

        $this->assertSame('smtp.sendgrid.net', $GLOBALS['_config']->mailing->host);
        $this->assertSame('File Sender', $GLOBALS['_config']->mailing->from_name);
    }

    private function failOnLog(): callable
    {
        return static function (string $message): void {
            throw new RuntimeException('The row was usable, so nothing should be recorded: ' . $message);
        };
    }
}
