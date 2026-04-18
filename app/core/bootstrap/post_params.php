<?php
use App\Helpers\ApiResponse;

function payload_decode() {
	/**
	* Obtiene el input en formato json y lo convierte en un objeto php
	*/
	try {
		$params = json_decode(file_get_contents('php://input'));
	} catch (\Exception $e) {
		error_logs(['Clean data error', $e->getMessage(), __LINE__, __FILE__]);
		ApiResponse::Set(900002);
	}
	$_payload = new stdClass();
	if (!empty($params)) {
		foreach ($params as $key => $value) {
			$k = trim($key);
			if (is_array($value) || is_object($value)) {
				$_payload->{$k} = json_encode($value);
			} else {
				$_payload->{$k} = $value;
			}
		}
		if(empty($params) || !$_payload) {
			error_logs([MODULE, 'No payload received', __FILE__, __LINE__]);
			ApiResponse::Set(400001);
		}
		return $_payload;
	}
}
$_payload = new stdClass();
if(in_array(REQUEST_TYPE, ['update', 'store'])) {
	$_payload = payload_decode();
}
?>
