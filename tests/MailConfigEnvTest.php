<?php

use PHPUnit\Framework\TestCase;

require_once CORE_PATH . 'config/mail_env.php';

/**
 * Guards the mail environment overlay: present variables (including empty)
 * replace the file values, absent variables keep them, the port is an int,
 * and the authentication flag is parsed from a truthy set.
 */
final class MailConfigEnvTest extends TestCase
{
    private function mailing(array $overrides = []): object
    {
        return (object) array_merge([
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

    private function env(array $vars): callable
    {
        return static fn(string $name) => $vars[$name] ?? null;
    }

    public function testDefinedValuesOverrideFileValues(): void
    {
        $mailing = $this->mailing();

        apply_mail_env_overrides($mailing, $this->env([
            'MAIL_HOST'       => 'mailpit',
            'MAIL_PORT'       => '1025',
            'MAIL_SECURITY'   => 'tls',
            'MAIL_USER'       => 'env-user',
            'MAIL_PASSWORD'   => 'env-secret',
            'MAIL_AUTH'       => 'true',
            'MAIL_FROM_EMAIL' => 'env@example.com',
            'MAIL_FROM_NAME'  => 'Env Sender',
        ]));

        $this->assertSame('mailpit', $mailing->host);
        $this->assertSame(1025, $mailing->port);
        $this->assertSame('tls', $mailing->security);
        $this->assertSame('env-user', $mailing->user);
        $this->assertSame('env-secret', $mailing->password);
        $this->assertTrue($mailing->smtp_auth);
        $this->assertSame('env@example.com', $mailing->from_email);
        $this->assertSame('Env Sender', $mailing->from_name);
    }

    public function testPresentEmptyValueOverridesFileValue(): void
    {
        $mailing = $this->mailing(['security' => 'ssl']);

        apply_mail_env_overrides($mailing, $this->env([
            'MAIL_SECURITY' => '',
            'MAIL_USER'     => '',
            'MAIL_PASSWORD' => '',
        ]));

        $this->assertSame('', $mailing->security);
        $this->assertSame('', $mailing->user);
        $this->assertSame('', $mailing->password);
    }

    public function testAbsentValuesFallBackToFileValues(): void
    {
        $mailing = $this->mailing();

        apply_mail_env_overrides($mailing, $this->env([]));

        $this->assertSame('smtp.sendgrid.net', $mailing->host);
        $this->assertSame(465, $mailing->port);
        $this->assertSame('ssl', $mailing->security);
        $this->assertSame('apikey', $mailing->user);
        $this->assertSame('file-secret', $mailing->password);
        $this->assertTrue($mailing->smtp_auth);
        $this->assertSame('file@example.com', $mailing->from_email);
        $this->assertSame('File Sender', $mailing->from_name);
    }

    public function testFalseyAuthDisablesAuthentication(): void
    {
        foreach (['', '0', 'false', 'off', 'no'] as $falsey) {
            $mailing = $this->mailing();

            apply_mail_env_overrides($mailing, $this->env(['MAIL_AUTH' => $falsey]));

            $this->assertFalse($mailing->smtp_auth, "MAIL_AUTH={$falsey} should disable authentication");
        }
    }

    public function testTruthyAuthEnablesAuthentication(): void
    {
        foreach (['1', 'true', 'on', 'yes'] as $truthy) {
            $mailing = $this->mailing(['smtp_auth' => false]);

            apply_mail_env_overrides($mailing, $this->env(['MAIL_AUTH' => $truthy]));

            $this->assertTrue($mailing->smtp_auth, "MAIL_AUTH={$truthy} should enable authentication");
        }
    }

    public function testPortIsCastToInteger(): void
    {
        $mailing = $this->mailing();

        apply_mail_env_overrides($mailing, $this->env(['MAIL_PORT' => '2525']));

        $this->assertSame(2525, $mailing->port);
    }

    public function testMailpitLocalDefaultsProducePlainTransport(): void
    {
        $mailing = $this->mailing();

        apply_mail_env_overrides($mailing, $this->env([
            'MAIL_HOST'     => 'mailpit',
            'MAIL_PORT'     => '1025',
            'MAIL_SECURITY' => '',
            'MAIL_USER'     => '',
            'MAIL_PASSWORD' => '',
            'MAIL_AUTH'     => 'false',
        ]));

        $this->assertSame('mailpit', $mailing->host);
        $this->assertSame(1025, $mailing->port);
        $this->assertSame('', $mailing->security);
        $this->assertSame('', $mailing->user);
        $this->assertSame('', $mailing->password);
        $this->assertFalse($mailing->smtp_auth);
    }

    public function testDefaultLookupReadsProcessEnvironment(): void
    {
        $name     = 'MAIL_FROM_NAME';
        $original = getenv($name);

        putenv($name . '=Process Sender');
        try {
            $mailing = $this->mailing();

            apply_mail_env_overrides($mailing);

            $this->assertSame('Process Sender', $mailing->from_name);
        } finally {
            if ($original === false) {
                putenv($name);
            } else {
                putenv($name . '=' . $original);
            }
        }
    }
}
