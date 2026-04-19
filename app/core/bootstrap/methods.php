<?php
use App\Helpers\ApiResponse;
use App\Auth\SessionSet;

function request_type() : string {
	global $_config;

	$request_type = $_SERVER['REQUEST_METHOD'];

	if ($request_type == 'OPTIONS') {
		exit;
	}

	if ($request_type == 'GET' && isset($_GET['id']) && preg_match('/([0-9]+)/m', $_GET['id'])) {
		$request_type = 'GETBYID';
	}

	$r_types = $_config->valid_requests;

	$type = $r_types->$request_type ?? NULL;

	if(!$type) {
		error_logs([$request_type, 405, 'Method not allowed', __LINE__, __FILE__]);
		ApiResponse::Set(900000);
	}

	if(in_array($type, ['show', 'update', 'destroy']) && (!isset($_GET['id']) || !is_numeric($_GET['id']) || empty($_GET['id']))) {
		error_logs([$type, 406, 'No entry id provided', __LINE__, __FILE__]);
		ApiResponse::Set(400002);
	}

	return $type;

}
SessionSet::UniqueIdentifierId();
define('REQUEST_TYPE', request_type());
?>
