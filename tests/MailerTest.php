<?php

use PHPUnit\Framework\TestCase;
use App\Helpers\ApiMailer;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

require_once CORE_PATH . 'helpers/custom_exceptions.php';
require_once CORE_PATH . 'helpers/phpmailer.php';

// AppException::__destruct() calls the global error_logs(); other test files
// also declare it, so define a no-op guard that is safe regardless of load order.
if (!function_exists('error_logs')) {
    function error_logs($data) {}
}

/**
 * Records the message assembled by ApiMailer without touching the network, and
 * can be told to reject the message so the failure path can be exercised.
 */
final class RecordingMailer extends PHPMailer
{
    public bool $rejected = false;
    public bool $sent = false;

    public function send()
    {
        $this->sent = true;
        if ($this->rejected) {
            $this->ErrorInfo = 'stub rejection';
            throw new MailerException('stub rejection');
        }
        return true;
    }
}

/**
 * Guards the mail-delivery contract: the helper configures its transport from
 * the `mailing` configuration section, tolerates absent optional fields, sends
 * UTF-8 content, reads the sender from the caller, and signals failures with
 * AppException(903000).
 */
final class MailerTest extends TestCase
{
    private mixed $originalConfig = null;

    protected function setUp(): void
    {
        ApiMailer::$transport = null;
        $this->originalConfig = $GLOBALS['_config'] ?? null;
    }

    protected function tearDown(): void
    {
        ApiMailer::$transport = null;
        $GLOBALS['_config'] = $this->originalConfig;
    }

    private function configureMailing(): void
    {
        $GLOBALS['_config'] = (object) [
            'mailing' => (object) [
                'debug'      => false,
                'host'       => 'mailpit',
                'port'       => 1025,
                'security'   => 'tls',
                'user'       => 'smtp-user',
                'password'   => 'smtp-pass',
                'smtp_auth'  => true,
                'from_email' => 'dev@chavodigital.com',
                'from_name'  => 'Chavo Digital',
            ],
        ];
    }

    private function minimalParams(): array
    {
        return [
            'from'      => ['email' => 'soporte@karewa.org.mx', 'name' => 'Soporte Karewa'],
            'to'        => [['email' => 'user@example.com', 'name' => 'User']],
            'subject'   => 'Código de recuperación',
            'html_body' => '<p>Hola áéíóú</p>',
            'text_body' => 'Hola',
        ];
    }

    private function sendWith(RecordingMailer $mail, array $params): void
    {
        ApiMailer::$transport = fn() => $mail;
        ApiMailer::Send($params);
    }

    public function testConfiguresTransportFromMailingSection(): void
    {
        $this->configureMailing();
        $mail = new RecordingMailer(true);

        $this->sendWith($mail, $this->minimalParams());

        $this->assertTrue($mail->sent);
        $this->assertSame('mailpit', $mail->Host);
        $this->assertSame(1025, $mail->Port);
        $this->assertSame('tls', $mail->SMTPSecure);
        $this->assertSame('smtp-user', $mail->Username);
        $this->assertSame('smtp-pass', $mail->Password);
        $this->assertTrue($mail->SMTPAuth);
        $this->assertSame('UTF-8', $mail->CharSet);
    }

    public function testSenderComesFromCallerAndIsApplied(): void
    {
        $this->configureMailing();
        $mail = new RecordingMailer(true);

        $this->sendWith($mail, $this->minimalParams());

        $this->assertSame('soporte@karewa.org.mx', $mail->From);
        $this->assertSame('Soporte Karewa', $mail->FromName);
        $this->assertSame('Código de recuperación', $mail->Subject);
        $this->assertSame('<p>Hola áéíóú</p>', $mail->Body);
        $this->assertSame('Hola', $mail->AltBody);
        $this->assertSame(
            [['user@example.com', 'User']],
            $mail->getToAddresses()
        );
    }

    public function testAbsentOptionalFieldsAreTolerated(): void
    {
        $this->configureMailing();
        $mail = new RecordingMailer(true);

        $this->sendWith($mail, $this->minimalParams());

        $this->assertTrue($mail->sent);
        $this->assertSame([], $mail->getCcAddresses());
        $this->assertSame([], $mail->getBccAddresses());
        $this->assertSame([], $mail->getReplyToAddresses());
        $this->assertSame([], $mail->getAttachments());
    }

    public function testProvidedOptionalFieldsAreApplied(): void
    {
        $this->configureMailing();
        $mail = new RecordingMailer(true);

        $attachment = tempnam(sys_get_temp_dir(), 'karewa-mail-');
        file_put_contents($attachment, 'report');

        try {
            $params = $this->minimalParams();
            $params['reply_to']     = ['email' => 'reply@example.com', 'name' => 'Reply'];
            $params['cc']           = [['email' => 'cc@example.com', 'name' => 'CC']];
            $params['bcc']          = [['email' => 'bcc@example.com', 'name' => 'BCC']];
            $params['attachments']  = [['file' => $attachment, 'name' => 'report.pdf']];

            $this->sendWith($mail, $params);

            $this->assertSame([['reply@example.com', 'Reply']], array_values($mail->getReplyToAddresses()));
            $this->assertSame('cc@example.com', $mail->getCcAddresses()[0][0]);
            $this->assertSame('bcc@example.com', $mail->getBccAddresses()[0][0]);
            $this->assertCount(1, $mail->getAttachments());
        } finally {
            @unlink($attachment);
        }
    }

    public function testTransportRejectionSignalsFailure(): void
    {
        $this->configureMailing();
        $mail = new RecordingMailer(true);
        $mail->rejected = true;

        ApiMailer::$transport = fn() => $mail;

        $this->expectException(\AppException::class);
        $this->expectExceptionCode(903000);

        ApiMailer::Send($this->minimalParams());
    }
}
