<?php
namespace App\Auth;
/**
 * Autenticacion por medio de un hash utilizable en enlaces el cual permite que solo quien cuente con el enlace pueda ver la informacion proporcionada, este es un hash unico y solo funciona con el enlace proporcionado
 **/
abstract class HashAuth {
	/**
	 * Codifica un payload (campos del recurso mas la expiracion) en la cadena
	 * que se firma. Se usa tanto al crear como al validar un token para que la
	 * codificacion no pueda divergir entre ambos lados.
	 */
	private static function encodePayload(array $payload) : string {
		ksort($payload);
		$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		return self::base64UrlEncode((string) $json);
	}

	/**
	 * Se crea un token firmado (HMAC-SHA256) sobre el payload del recurso. El
	 * payload incluye la expiracion derivada de la ventana configurable, por lo
	 * que un token solo es valido para el recurso con el que fue creado.
	 * La salida es URL-safe para poder viajar en enlaces.
	 */
	public static function Create(array $hashArray = [], ?int $ttl = null) : string {
		$hashArray['exp'] = time() + ($ttl ?? self::window());
		$body = self::encodePayload($hashArray);
		return $body .'.' .self::sign($body);
	}

	/**
	 * Valida un token leido desde el request (header o `_key`) contra el payload
	 * del recurso: la firma debe ser integra, el token no debe haber expirado y
	 * el payload presentado debe coincidir con el payload firmado.
	 */
	public static function Validate(array $hashArray = []) : bool {
		$witness = self::witness();
		if($witness === '') {
			return false;
		}
		$parts = explode('.', $witness);
		if(count($parts) !== 2) {
			return false;
		}
		[$body, $signature] = $parts;
		$claims = json_decode(self::base64UrlDecode($body), true);
		if(!is_array($claims)) {
			return false;
		}
		if(!hash_equals(self::sign($body), $signature)) {
			return false;
		}
		if(!isset($claims['exp']) || (int) $claims['exp'] < time()) {
			return false;
		}
		unset($claims['exp']);
		if(self::encodePayload($claims) !== self::encodePayload($hashArray)) {
			return false;
		}
		return true;
	}

	/**
	 * Lee el token (witness) desde el header y, si no viene, desde el parametro
	 * legacy `_key` para no romper los enlaces existentes.
	 */
	private static function witness() : string {
		$headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
		$value = $headers['X-Hash-Auth']
			?? $headers['x-hash-auth']
			?? ($_SERVER['HTTP_X_HASH_AUTH'] ?? '');
		if(($value === '' || $value === null) && isset($_GET['_key'])) {
			$value = $_GET['_key'];
		}
		return is_string($value) ? trim(strip_tags($value)) : '';
	}

	private static function sign(string $data) : string {
		return self::base64UrlEncode(hash_hmac('sha256', $data, self::secret(), true));
	}

	private static function secret() : string {
		return defined('HASH_AUTH_PASS') ? (string) constant('HASH_AUTH_PASS') : '';
	}

	private static function window() : int {
		return defined('HASH_AUTH_EXP') ? (int) constant('HASH_AUTH_EXP') : 3600;
	}

	private static function base64UrlEncode(string $data) : string {
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	private static function base64UrlDecode(string $data) : string {
		$data = strtr($data, '-_', '+/');
		$remainder = strlen($data) % 4;
		if($remainder) {
			$data .= str_repeat('=', 4 - $remainder);
		}
		return (string) base64_decode($data);
	}
}
?>
