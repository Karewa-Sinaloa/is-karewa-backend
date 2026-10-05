<?php

use PHPUnit\Framework\TestCase;
use App\Auth\HashAuth;

if (!defined('HASH_AUTH_PASS')) {
    define('HASH_AUTH_PASS', 'hash-auth-test-secret');
}

require_once CORE_PATH . 'auth/hash.auth.php';

/**
 * Covers the alternative hash token lifecycle: a token is bound to its payload,
 * expires, and its witness is read from a header with a legacy `_key` fallback.
 * HASH_AUTH_EXP is intentionally left undefined so these cases exercise the
 * default expiration window.
 */
final class HashAuthTest extends TestCase
{
    protected function setUp(): void
    {
        unset($_GET['_key'], $_SERVER['HTTP_X_HASH_AUTH']);
    }

    protected function tearDown(): void
    {
        unset($_GET['_key'], $_SERVER['HTTP_X_HASH_AUTH']);
    }

    public function testRoundTripSucceeds(): void
    {
        $token = HashAuth::Create(['id' => 42]);

        $this->assertNotSame('', $token);
        $this->assertStringNotContainsString('=', $token, 'Token output must be URL-safe');

        $_GET['_key'] = $token;

        $this->assertTrue(HashAuth::Validate(['id' => 42]));
    }

    public function testDifferentPayloadFails(): void
    {
        $token = HashAuth::Create(['id' => 42]);

        $_GET['_key'] = $token;

        $this->assertFalse(HashAuth::Validate(['id' => 43]));
    }

    public function testExpiredTokenFails(): void
    {
        $token = HashAuth::Create(['id' => 42], -10);

        $_GET['_key'] = $token;

        $this->assertFalse(HashAuth::Validate(['id' => 42]));
    }

    public function testHeaderWitnessWorks(): void
    {
        $token = HashAuth::Create(['id' => 42]);

        $_SERVER['HTTP_X_HASH_AUTH'] = $token;

        $this->assertTrue(HashAuth::Validate(['id' => 42]));
    }

    public function testKeyFallbackWorks(): void
    {
        $token = HashAuth::Create(['id' => 42]);

        $_GET['_key'] = $token;

        $this->assertTrue(HashAuth::Validate(['id' => 42]));
    }

    public function testMissingWitnessFails(): void
    {
        HashAuth::Create(['id' => 42]);

        $this->assertFalse(HashAuth::Validate(['id' => 42]));
    }
}
