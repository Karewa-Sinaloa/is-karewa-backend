<?php
namespace App\Helpers;
/**
 * $options = array(
 *  curlopt_returntransfer => true,       // return web page
 *  curlopt_header         => false,      // don't return headers
 *  curlopt_followlocation => true,       // follow redirects
 *  curlopt_encoding       => '',         // handle all encodings
 *  curlopt_useragent      => 'spider',   // who am i
 *  curlopt_autoreferer    => true,       // set referer on redirect
 *  curlopt_connecttimeout => 120,        // timeout on connect
 *  curlopt_timeout        => 120,        // timeout on response
 *  curlopt_maxredirs      => 10,         // stop after 10 redirects
 *  curlopt_post           => 1,          // i am sending post data
 *  curlopt_postfields     => $curl_data, // this are my post vars
 *  curlopt_ssl_verifyhost => 0,          // don't verify ssl
 *  curlopt_ssl_verifypeer => false,      //
 *  curlopt_verbose        => 1,          //
 *);
 *
 *$ch = curl_init($url);
 *curl_setopt_array($ch, $options);
 *$content = curl_exec($ch);
 *$err     = curl_errno($ch);
 *$errmsg  = curl_error($ch);
 *$header  = curl_getinfo($ch);
 *curl_close($ch);
 */
abstract class HCaptcha {
	/**
	 * Seam for the upstream HTTP call. Defaults to CurlRequest::Init; tests may
	 * replace it to avoid the network. Kept public on purpose for that override.
	 * @var callable|null
	 */
	public static $transport = null;

	static public function Validate(String $token) {
		global $_config;
		$url =	$_config->hcaptcha->url;
		$transport = self::$transport ?? [CurlRequest::class, 'Init'];
		$response = $transport([
			CURLOPT_URL	=> $url,
			CURLOPT_POST => 1,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POSTFIELDS => [
				'response' => $token,
				'secret' => $_config->hcaptcha->secret
			]
		]);
		return self::HandleResponse($response);
	}

	/**
	 * Acepta la respuesta del servicio solo cuando es un payload de éxito bien
	 * formado; de lo contrario falla en cerrado con el código de error de
	 * terceros (905000), sin acceder a un cuerpo nulo o mal decodificado.
	 */
	static public function HandleResponse(array $response) {
		$raw  = $response['body'] ?? '';
		$body = is_string($raw) ? json_decode($raw) : null;
		if (!is_object($body) || ($body->success ?? false) !== true) {
			throw new \AppException(is_string($raw) ? $raw : '', 905000);
		}
		return $response;
	}
}
?>
