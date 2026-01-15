<?php
namespace App\Auth;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\UnexpectedValueException;

abstract class jwtToken {

	private static function getKeys(string $keyType) {
		$privateKey = JWTKEYS_PATH . 'jwtRS256.key';
		$publicKey = JWTKEYS_PATH . 'jwtRS256.key.pub';
		if(!file_exists($publicKey) || !file_exists($privateKey)) {
			throw new \AppException('Public and/or private key does not exist or file has incorrect permissions', 901007);
		}
    switch ($keyType) {
    case 'private':
      $key = file_get_contents($privateKey);
      break;
    case 'public':
      $key = file_get_contents($publicKey);
      break;
		}
		if ($key == false) {
      throw new \AppException('JWT ' . $keyType . ' Key not found', 901000);
    }
    return $key;
  }

  public static function encode(array $data, bool $keep_session = false) {
    $time = time();
		$exp  = $time + SESSION_TIME;
		if($keep_session) {
			$exp = $time + 31536000;
		}

    $public = self::getKeys('private');

    $token_data = [
      'iat'     => $time,
      'exp'     => $exp,
      'message' => MESSAGE,
      'data'    => [
        'id'         => $data['id'],
        'first_name' => $data['first_name'],
        'last_name'  => $data['last_name'],
        'email'      => $data['email'],
        'role_id'    => $data['role_id'],
      ],
    ];

		try {
			$token = JWT::encode($token_data, $public, JWT_ENCODING);
		} catch(\Exception $e) {
			throw new \AppException('Could not encode JWT token', 901000);
		}
    return (object) [
      'token'      => $token,
      'expiration' => $exp
    ];
  }

  public static function decode($jwt) {
		$j = preg_replace('/Bearer /', '', (string) $jwt);
		$tData  = null;
		$status = false;
    if ($j && !empty($j)) {
			$private = self::getKeys('public');
			try {
				$key = new Key($private, JWT_ENCODING);
				$jwtData = JWT::decode($j, $key);
      } catch (ExpiredException $e) {
      	throw new \AppException('JWT error expired, message: ' . $e->getMessage(), 901001);
      } catch (UnexpectedValueException $e) {
      	throw new \AppException('JWT unexpected values, message: ' . $e->getMessage(), 901002);
      } catch (SignatureInvalidException $e) {
      	throw new \AppException('JWT invalid signature, message: ' . $e->getMessage(), 901003);
      } catch (BeforeValidException $e) {
      	throw new \AppException('JWT before valid, message: ' . $e->getMessage(), 901001);
      } catch (\Exception $e) {
      	throw new \AppException('JWT other exception, message: ' . $e->getMessage(), 901004);
      }

      $currTime = time();

      if ($jwtData->message == MESSAGE && $currTime < $jwtData->exp) {
        $tData  = $jwtData;
        $status = true;
      }
    }
    return (object) [
      'token_data' => $tData,
      'status'     => $status,
    ];
  }
}
?>
