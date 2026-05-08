<?php
namespace App\Auth;
require_once __DIR__ . '/jwt_token.php';
use App\Auth\jwtToken;
use Ramsey\Uuid\Uuid;
use App\Helpers\ApiResponse;

abstract class SessionSet {
  /**
   * Genera un nuevo Token cuando se realiza el login y lo devuelve en la respuesta con los datos necesarios
   * @param  array  $session_data Datos del usuario
   * @return array  Datos de inicio de session junto al Token
   */
	public static function Login(array $session_data, bool $keep_session = false) {
		$jwt = null;
		try {
			$jwt = jwtToken::encode($session_data, $keep_session);
		} catch(\AppException $e) {
			ApiResponse::Set($e->errorCode());
		}

    define('USER_ROLE', $session_data['role_id']);

    return [
      'access_token' => $jwt->token,
      'expires_in'   => (int) $jwt->expiration,
      'role_id'      => (int) $session_data['role_id'],
      'name'         => $session_data['first_name'],
      'last_name'    => $session_data['last_name'],
      'email'        => $session_data['email'],
      'user_id'      => (int) $session_data['id'],
    ];
  }

  public static function Validate(?string $access_token = NULL) : bool {
    $access_granted = false;

		$jwt_validation = jwtToken::decode($access_token);

		$access_granted = false;
		if($jwt_validation->status) {
			$access_granted = $jwt_validation->status;
			define('USER_ROLE', $jwt_validation->token_data->data->role_id);
			define('USER_ID', $jwt_validation->token_data->data->id);
			define('USER_NAME', $jwt_validation->token_data->data->first_name);
			define('USER_LASTNAME', $jwt_validation->token_data->data->last_name);
			define('USER_EMAIL', $jwt_validation->token_data->data->email);
			define('EXPIRATION', $jwt_validation->token_data->exp);
		} else {
			define('USER_ID', IDENTIFIER_UID);
		}
		return $access_granted;
	}
	
	static public function UniqueIdentifierId() {
		$headers = apache_request_headers();
		if (!$headers['x-identifier'] || !isset($headers['x-identifier']) || $headers['x-identifier'] == 'undefined') {
			$uid = Uuid::uuid4();
			header('x-identifier:' . $uid);
		} else {
			$uid = $headers['x-identifier'];
		}
		define('IDENTIFIER_UID', $uid);
	}
}
?>
