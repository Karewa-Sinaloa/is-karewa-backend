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
			$this->assertArrayHasKey('jti', $token);
			$this->assertArrayHasKey('message', $token);
			$this->assertArrayHasKey('data', $token);
			$this->assertNotEmpty($token['jti']);
			$this->assertSame($token['jti'], $token_data->jti);
			$this->assertNotSame('', (string) $token_data->jti);
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

	public function testJWTKeyEncodeAssignsDistinctJtiAndDecodes() : void {
		$data = [
			'id' => 1,
			'first_name' => 'John',
			'last_name' => 'Doe',
			'email' => 'user@domain.com',
			'role_id' => 1,
		];

		$first  = jwtToken::encode($data);
		$second = jwtToken::encode($data);

		$this->assertNotEmpty($first->jti);
		$this->assertNotEmpty($second->jti);
		$this->assertNotSame($first->jti, $second->jti);

		$decoded = jwtToken::decode($first->token);
		$this->assertTrue($decoded->status);
		$this->assertSame($first->jti, $decoded->token_data->jti);
	}
}
?>
