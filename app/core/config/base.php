<?php
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
/**
 * Direccion del directorio ROOT
 */
$root_dir = $_SERVER['DOCUMENT_ROOT'] . '/';
$app_path = preg_replace('/core\/config\/?/m', '', __DIR__);
$core_path = preg_replace('/config\/?/m', '', __DIR__);
// GLOBALS
$_config = NULL;
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
ini_set('display_errors', $_config->development);
ini_set('display_startup_errors', $_config->development);
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
ini_set('error_log', ERROR_LOG_FILE);
ini_set('session.use_cookies', 0);
date_default_timezone_set($_config->timezone);
/**
 * Configuracion de los headers del API
 */
header('Access-Control-Allow-Headers: X-Requested-With, Authorization, Content-Type, X-PINGOTHER, X-Identifier');
if ($_config->cors->active) {
  header('Access-Control-Allow-Origin: *');
} else {
  $http_origin = $_SERVER['HTTP_ORIGIN'];
  if (in_array($http_origin, $_config->cors->domains)) {
    header('Access-Control-Allow-Origin: ' . $http_origin);
  }
}
header('Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS');
header('Pragma: no-cache');
header('Content-Type: application/json; charset=utf8mb4');
header("P3P: CP='IDC DSP COR CURa ADMa OUR IND PHY ONL COM STA'");
header('Access-Control-Allow-Credentials: true');
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
define('MYSQL_HOST', $_config->database->host);
define('MYSQL_DB', $_config->database->database);
define('MYSQL_USER', $_config->database->user);
define('MYSQL_PSWD', $_config->database->password);
define('MYSQL_PORT', $_config->database->port);
define('MYSQL_CHARSET', $_config->database->charset);
define('MYSQL_COLLATION', $_config->database->collation);
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
 * REC_CODE_TIME Tiempo de expiración del código de recuperacíon de acceso o validación de correo electrónico
 */
define('REC_CODE_TIME', $_config->access->code_expiration_time);
/**
 * CART_EXPIRATION Tiempo de expiración del código de recuperacíon de acceso o validación de correo electrónico
 */
define('CART_EXPIRATION', $_config->cart->expiration);
/**
 * Facebook SDK Graph config
 */
define('FB_API_ID', $_config->facebook->appId);
define('FB_API_SECRET', $_config->facebook->secret);
define('FB_API_VERSION', $_config->facebook->version);

if ($_config->development == true) {
	define('DEVELOPMENT', $_config->development);
}
// URLS
define('API_URL', $_config->api);
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

?>
