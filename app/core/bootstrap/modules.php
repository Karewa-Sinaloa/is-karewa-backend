<?php
use App\Helpers\ApiResponse;

function api_modules() {
  /**
   * Valida que el modulo exista y lo llama
	 */
  $module_name = trim(strip_tags((string) $_GET['m']));
	$module_dir = ROOT_PATH . 'api/' . $module_name;
	$core_module_dir = CORE_PATH . 'modules/' . $module_name;

  if (!empty($module_name) && (is_dir($module_dir) || is_dir($core_module_dir))) {
		define('MODULE', $module_name);
		if(is_dir($core_module_dir)) {
			return $core_module_dir;
		}
    return $module_dir;
  } else {
		error_logs(['MISSING MODULE', 404, 'No module found', __LINE__, __FILE__]);
		ApiResponse::Set(900001);
  }
}
$module_dir = api_modules();
if(in_array(REQUEST_TYPE, ['update', 'store'])) {
	cleanData();
}
$response = require_once $module_dir . '/index.php';
?>
