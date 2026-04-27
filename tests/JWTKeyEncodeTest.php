<?php
use PHPUnit\Framework\TestCase;
use App\Auth\jwtToken;

define('MESSAGE', 'Token generated successfully on PHP Unit Test');

require_once CORE_PATH . 'auth/jwt_token.php';

final class JWTKeyEncodeTest extends TestCase
{
	public function testJWTKeyEncode() : void {

		try {
			$token_data = jwtToken::encode([
				'id' => 1,
				'first_name' => 'John',
				'last_name' => 'Doe',
				'email' => 'user@domain.com',
				'role_id' => 1
			]);
			
			$token = $token_data->raw;

			$this->assertIsArray($token);
			$this->assertArrayHasKey('iat', $token);
			$this->assertArrayHasKey('exp', $token);
			$this->assertArrayHasKey('message', $token);
			$this->assertArrayHasKey('data', $token);
			$this->assertEquals(MESSAGE, $token['message']);
			$this->assertIsArray($token['data']);
			$this->assertArrayHasKey('id', $token['data']);
			$this->assertArrayHasKey('first_name', $token['data']);
			$this->assertArrayHasKey('last_name', $token['data']);
			$this->assertArrayHasKey('email', $token['data']);
			$this->assertArrayHasKey('role_id', $token['data']);
			$this->assertEquals(1, $token['data']['id']);
			$this->assertEquals('John', $token['data']['first_name']);
			$this->assertEquals('Doe', $token['data']['last_name']);
		} catch (\AppException $e) {
			$this->assertInstanceOf(\AppException::class, $e);
		}
	}
}
?>
