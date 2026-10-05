<?php
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
require_once __DIR__ . '/mail_env.php';
/**
 * Direccion del directorio ROOT
 */
$root_dir = $_SERVER['DOCUMENT_ROOT'] . '/';
$app_path = preg_replace('/core\/config\/?/m', '', __DIR__);
$core_path = preg_replace('/config\/?/m', '', __DIR__);
// GLOBALS
$_config = new stdClass();
$_payload = NULL;
$_apiConfig = NULL;
// EOF GLOBALS
try {
	$yml_file = file_get_contents($app_path . 'config.yml');
	$_config = Yaml::parse($yml_file, Yaml::PARSE_OBJECT_FOR_MAP);
} catch(ParseException $e) {
	http_response_code(500);
	die(json_encode([
		'message' => 'Error parsing configuration file: ' .$e->getMessage()
	]));
} catch(\Exception $e) {
	http_response_code(500);
	die(json_encode([
		'message' => 'Error parsing configuration file: ' .$e->getMessage()
	]));
}
if (empty((array) $_config) || !is_object($_config)) {
	http_response_code(500);
	die(json_encode([
		'message' => 'Error loading configuration file'
	]));
}
/**
 * Los ajustes de correo pueden sobrescribirse con variables de entorno para
 * que un entorno local apunte a Mailpit sin editar config.yml.
 */
$_config->mailing = apply_mail_env_overrides(
	is_object($_config->mailing ?? null) ? $_config->mailing : new stdClass()
);
/**
 * Ubicación del archivo de logs de errores del API
 */
define('DEBUG_LOG_PATH', $app_path . $_config->log->path);
define('ERROR_LOG_FILE', $app_path . $_config->log->path . $_config->log->error);
define('DEBUG_LOG_FILE', $app_path . $_config->log->path . $_config->log->debug);
define('PAYPAL_LOG_FILE', $app_path . $_config->log->path . $_config->log->paypal);
define('FRONTEND_LOG_FILE', $app_path . $_config->log->path . $_config->log->frontend);
/**
 * Guarda los errores en el archivo definido para este fin
 */
ini_set('log_errors', 'On');
ini_set('html_errors', 'On');
ini_set('display_errors', $_config->development);
ini_set('display_startup_errors', $_config->development);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
ini_set('error_log', ERROR_LOG_FILE);
ini_set('session.use_cookies', 0);
date_default_timezone_set($_config->timezone);
/**
 * Configuracion de los headers del API
 */
header('Access-Control-Allow-Headers: X-Requested-With, Authorization, Content-Type, X-PINGOTHER, X-Identifier');
header('Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS');
header('Pragma: no-cache');
header('Content-Type: application/json; charset=utf8mb4');
header("P3P: CP='IDC DSP COR CURa ADMa OUR IND PHY ONL COM STA'");

/**
 * CORS con lista blanca explicita.
 *
 * - Solo se conceden los origenes listados en cors.domains.
 * - Nunca se combina el comodin "*" con Access-Control-Allow-Credentials.
 * - Un origen no permitido se rechaza con un estado HTTP real.
 * - El modo de desarrollo (cors.wildcard) permite cualquier origen pero
 *   desactiva las credenciales.
 */
$http_origin   = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_hosts = (array) ($_config->cors->domains ?? []);
$wildcard      = (bool) ($_config->cors->wildcard ?? false);
$origin_ok     = false;

if ($http_origin !== '' && in_array($http_origin, $allowed_hosts, true)) {
  header('Access-Control-Allow-Origin: ' . $http_origin);
  header('Access-Control-Allow-Credentials: true');
  header('Vary: Origin');
  $origin_ok = true;
} elseif ($wildcard) {
  // Desarrollo: cualquier origen, sin credenciales.
  header('Access-Control-Allow-Origin: *');
} elseif ($http_origin !== '') {
  // Origen presente pero no listado: rechazo con estado HTTP.
  http_response_code(403);
  die(json_encode([
    'message'   => 'CORS policy: This origin is not allowed',
    'code'      => 'APP_CORS_ORIGIN_DENIED',
    'http_code' => 403,
  ]));
}

/**
 * Cabeceras de seguridad estandar, emitidas de forma central.
 */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: ' . (string) ($_config->security->headers->frame_options ?? 'DENY'));
header('Referrer-Policy: ' . (string) ($_config->security->headers->referrer_policy ?? 'no-referrer'));
$is_https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
  || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
  || (($_config->https ?? false) && ($_config->security->headers->hsts ?? false));
if ($is_https && ($_config->security->headers->hsts ?? true)) {
  $hsts_max_age = (int) ($_config->security->headers->hsts_max_age ?? 31536000);
  header('Strict-Transport-Security: max-age=' . $hsts_max_age . '; includeSubDomains');
}
/** Dirección donde se encuentran las claves privadas y publicas para encriptar el token JWT
 */
define('JWTKEYS_PATH', $app_path . '.keys/');
/**
 * PATHS
 */
define('ROOT_PATH', $app_path);
define('CORE_PATH', $core_path);
define('ROOT_DIR', $root_dir);
/**
 * MYSQL DATA
 */
define('MYSQL_HOST', $_config->database->host ?? 'localhost');
define('MYSQL_DB', $_config->database->database);
define('MYSQL_USER', $_config->database->user);
define('MYSQL_PSWD', $_config->database->password);
define('MYSQL_PORT', $_config->database->port ?? 3306);
define('MYSQL_PREFIX', $_config->database->prefix ?? '');
define('MYSQL_CHARSET', $_config->database->charset ?? 'utf8mb4');
define('MYSQL_COLLATION', $_config->database->collation ?? 'utf8mb4_unicode_ci');
/**
 * MESSAGE Mensaje que se agrega al Token JTW
 */
define('MESSAGE', $_config->jwt->message);
define('JWT_ENCODING', $_config->jwt->encoding);
/**
 * SESSION_TIME tiempo que dura la sesión activa
 */
define('SESSION_TIME', $_config->session->time);
define('UID_PREFIX', $_config->session->prefix);
/**
 * SESSION_BLACKLIST_RETENTION segundos adicionales que se conserva una entrada
 * de la lista negra después de la expiración del token; 0 conserva solo hasta la
 * expiración.
 */
define('SESSION_BLACKLIST_RETENTION', (int) ($_config->session->blacklist_retention ?? 0));
/**
 * REC_CODE_TIME Tiempo de expiración del código de recuperacíon de acceso o validación de correo electrónico
 */
define('REC_CODE_TIME', $_config->access->code_expiration_time);
/**
 * CART_EXPIRATION Tiempo de expiración del código de recuperacíon de acceso o validación de correo electrónico
 */
define('CART_EXPIRATION', $_config->cart->expiration);

if ($_config->development == true) {
	define('DEVELOPMENT', $_config->development);
}
// URLS
// The api node is a map (url + https); build an absolute URL string from it.
$api_node = $_config->api ?? null;
if (is_object($api_node)) {
	$api_url    = (string) ($api_node->url ?? '');
	$api_scheme = !empty($api_node->https) ? 'https' : 'http';
	if ($api_url !== '' && !str_contains($api_url, '://')) {
		$api_url = $api_scheme . '://' . $api_url;
	}
} else {
	$api_url = (string) $api_node;
}
define('API_URL', $api_url);
define('SITE_URL', $_config->domain);
define('CMS_URL', $_config->cms);
// STATIC FILES URI PATH
define('STATIC_URL', $_config->statics->url);
define('STATIC_PATH', $_config->statics->path);
define('STATIC_IMAGES_PATH', $_config->statics->images);
define('STATIC_ATTACH_PATH', $_config->statics->attachments);
//MAILINGS
define('MAILING_UUID', $_config->mailings->uuid);
define('MAILINGS_URL', $_config->mailings->url);
define('MAILINGS_HASH', $_config->mailings->hash);

define('HASH_AUTH_PASS', $_config->hash);
/**
 * HASH_AUTH_EXP ventana de validez, en segundos, de los tokens de autenticación
 * alternativa por hash. Un valor ausente en config.yml usa 3600 (una hora).
 */
define('HASH_AUTH_EXP', (int) ($_config->hash_expiration ?? 3600));

?>
