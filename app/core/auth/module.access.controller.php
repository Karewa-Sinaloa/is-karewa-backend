<?php
namespace App\Auth;
use App\Auth\SessionSet;
use App\Auth\HashAuth;
use App\Helpers\ApiResponse;

abstract class ModuleHandler {

	public static function Validate(array $accepted_methods, Object $class): void {
		$rType = REQUEST_TYPE;
		$auth = false;
		$roles = [];
		$alias = '';
		$method = '';
		$hash = '';
		// Verifica si el método solicitado existe en la clase y está permitido
		if (array_key_exists($rType, $accepted_methods) && method_exists($class, $rType)) {
			// Si el método existe, obtiene la configuración de autenticación y roles permitidos para ese método, sino se omite la autenticación y roles permitidos para ese método, lo que significa que no se requiere autenticación ni roles específicos para acceder a ese método
			$auth  = $accepted_methods[$rType][0] ?? false;
			$roles = isset($accepted_methods[$rType][1]) ? $accepted_methods[$rType][1] : [];
			// Si se ha definido un alias para el método, se utiliza en lugar del nombre del método solicitado, se utilizara siempre que se quiera realizar un procedimiento extra y saltarse el TRAIT ya que por defecto se ejecuta el método con el mismo nombre del REQUEST_TYPE, pero si se quiere realizar un procedimiento extra antes o después del método se puede definir un alias para que se ejecute el método con ese nombre y no el del REQUEST_TYPE
			$alias = isset($accepted_methods[$rType][3]) ? $accepted_methods[$rType][3] : '';
			// Si se ha definido un hash para el método, se utiliza para la autenticación alternativa, lo que permite que solo quien cuente con el enlace pueda ver la información proporcionada, este es un hash único y solo funciona con el enlace proporcionado, esto es útil para webhooks o enlaces compartidos que no requieren autenticación tradicional pero si una capa de seguridad adicional, se puede definir un hash para cada método que lo requiera, y este hash se validará en la función Authenticate
			$hash = isset($accepted_methods[$rType][2]) ? $accepted_methods[$rType][2] : '';	
			$method = $alias ?: $rType;
		} else {
			error_logs([MODULE, 405, 'No existe el método dentro de la clase especificada: ' . $rType]);
			ApiResponse::Set(900000);
		}
		self::Authenticate($auth, $roles, $hash);
		// Llama al método correspondiente de la clase
		$class->{$method}();
	}

	private static function Authenticate(bool $auth = false, array $roles = [], string $hash = ''): bool {
		$authenticated = false;
		// Verifica si se permite autenticación alternativa por hash, como en webhooks
		$altAuth = null;
		// TODO: Mejorar la forma de validar el hash, es necesario realizar los siguientes cambios:
		// 1. Cambiar la función HashAuth::Validate() para que reciba el hash como parámetro en el header en lugar de obtenerlo de $_GET, esto permitirá validar el hash sin depender de la URL y evitar posibles problemas de seguridad al exponer el hash en la URL.
		// 2. Pasar el valor del usuario o ID relacionado con el hash como un parámetro adicional a la función HashAuth::Validate(), esto permitirá validar el hash de manera más precisa y segura, asegurando que el hash corresponda al usuario o recurso específico que se está intentando acceder y ligar los permisos de acceso al hash, esto es especialmente útil para enlaces compartidos o webhooks donde no se requiere autenticación tradicional pero si una capa de seguridad adicional.
		// 3. Definir un tiempo de expiración para los hashes generados, esto mejorará la seguridad al limitar la validez de los hashes y reducir el riesgo de uso indebido en caso de que un hash sea comprometido, además de permitir una mejor gestión de los permisos de acceso a través de los hashes.
		// 4. Limitar el acceso por medio de webhooks a la o las direcciones IP específicas, esto se puede lograr validando la dirección IP del remitente en la función HashAuth::Validate() y comparándola con una lista de direcciones IP permitidas, esto mejorará la seguridad al asegurarse de que solo los remitentes autorizados puedan acceder a los recursos a través de webhooks, reduciendo el riesgo de accesos no autorizados.
		// Al realizar estos cambios no será neesario pasar el hash como un parámetro en la función Authenticate, sino que se podrá validar directamente desde los headers, lo que mejorará la seguridad y flexibilidad de la autenticación alternativa por hash.
		/*--
		if (!empty($hash)) {
			$altAuth = HashAuth::Validate($hash);
		}
		--*/
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
