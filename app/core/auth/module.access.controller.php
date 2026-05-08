<?php
namespace App\Auth;
use App\Auth\SessionSet;
use App\Auth\HashAuth;
use App\Helpers\ApiResponse;

abstract class ModuleHandler {

	public static function Validate(array $accepted_methods, Object $class): void {
		$rType = REQUEST_TYPE;
		$auth = false;
		$roles = NULL;
		$alias = null;
		$method = null;
		// Verifica si el método solicitado existe en la clase y está permitido
		if (array_key_exists($rType, $accepted_methods) && method_exists($class, $rType)) {
			$auth  = $accepted_methods[$rType][0];
			$roles = isset($accepted_methods[$rType][1]) ? $accepted_methods[$rType][1] : NULL;	
			$alias = isset($accepted_methods[$rType][3]) ? $accepted_methods[$rType][3] : NULL;	
			$hash = isset($accepted_methods[$rType][2]) ? $accepted_methods[$rType][2] : NULL;	
			$method = $alias ?: $rType;
		} else {
			error_logs([MODULE, 405, 'No existe el método dentro de la clase especificada: ' . $rType]);
			ApiResponse::Set(900000);
		}
		self::Authenticate($auth, $roles, $hash);
		// Llama al método correspondiente de la clase
		$class->{$method}();
	}

	private static function Authenticate($auth, array $roles = [], string $hash = ''): bool {
		$authenticated = false;
		// Verifica si se permite autenticación alternativa por hash, como en webhooks
		$altAuth = null;
		if (!empty($hash)) {
			$altAuth = HashAuth::Validate($hash);
		}
		$headers = apache_request_headers();
		$authSet = (isset($headers['Authorization']) && !empty($headers['Authorization'])) || (isset($headers['authorization']) && !empty($headers['authorization']));
		$access_token	= $headers['Authorization'] ?: $headers['authorization'];
		// Realiza la validación de autenticación si es requerida y si el token de acceso está presente, sino devuelve error
		if($auth && !$authSet) {
			error_logs([MODULE, 401, 'No access token provided', __LINE__, __FILE__]);
			ApiResponse::Set(901004);
		}
		// Si no se requiere autenticación o si se usó autenticación alternativa, permite el acceso
		if(!$auth || ($auth && $altAuth)) {
			if($auth && $altAuth) {
				define('AUTHENTICATED', true);
			} else {
				define('AUTHENTICATED', false);
			}
			return true;
		}
		// Si se necesita autenticación, valida el token de acceso y los roles si se especifican
		$access_granted = false;
		try {
			$access_granted = SessionSet::Validate($access_token);
		} catch(\AppException $e) {
			ApiResponse::Set($e->errorCode());
		}
		$authenticated = $access_granted && (defined('USER_ROLE') && (count($roles) == 0 || (in_array(USER_ROLE, $roles))));
		
		if(!$authenticated) {
			error_logs([MODULE, 403, 'No access allowed', __LINE__, __FILE__]);
			ApiResponse::Set(901004);
		}
		define('AUTHENTICATED', true);
		return true;
	}	
}
?>
