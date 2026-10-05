<?php

use PHPUnit\Framework\TestCase;
use App\Helpers\HCaptcha;

require_once CORE_PATH . 'helpers/custom_exceptions.php';
require_once CORE_PATH . 'helpers/hcaptcha.php';

// AppException::__destruct() calls the global error_logs(); other test files
// also declare it, so define a no-op guard that is safe regardless of load order.
if (!function_exists('error_logs')) {
    function error_logs($data) {}
}

/**
 * Guards that hCaptcha verification fails closed: any upstream response that is
 * not a well-formed success payload, or a transport failure, must be rejected
 * with the third-party error code 905000 and never raise an unhandled error.
 */
final class HCaptchaVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        HCaptcha::$transport = null;
    }

    protected function tearDown(): void
    {
        HCaptcha::$transport = null;
    }

    public function testNonSuccessResponseIsRejected(): void
    {
        $this->expectException(\AppException::class);
        $this->expectExceptionCode(905000);

        HCaptcha::HandleResponse(['body' => '{"success":false}']);
    }

    public function testMalformedBodyIsRejected(): void
    {
        $this->expectException(\AppException::class);
        $this->expectExceptionCode(905000);

        HCaptcha::HandleResponse(['body' => 'not-json']);
    }

    public function testEmptyBodyIsRejected(): void
    {
        $this->expectException(\AppException::class);
        $this->expectExceptionCode(905000);

        HCaptcha::HandleResponse(['body' => '']);
    }

    public function testWellFormedSuccessIsAccepted(): void
    {
        $response = ['body' => '{"success":true}'];

        $this->assertSame($response, HCaptcha::HandleResponse($response));
    }

    public function testTransportFailureFailsClosed(): void
    {
        global $_config;
        $_config = (object) [
            'hcaptcha' => (object) [
                'url'    => 'https://hcaptcha.com/siteverify',
                'secret' => 'test-secret',
            ],
        ];

        HCaptcha::$transport = function (array $options) {
            throw new \AppException('transport down', 905000);
        };

        $this->expectException(\AppException::class);
        $this->expectExceptionCode(905000);

        HCaptcha::Validate('token');
    }
}
