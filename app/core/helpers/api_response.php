<?php
namespace App\Helpers;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

abstract class ApiResponse {
	public static function Set(string $code, array|null $data = NULL, array|null $response_options = null) : void {
		$options = $response_options ?? ['meta' => TRUE]; 
		$yaml_file = file_get_contents(CORE_PATH . 'config/api_codes.yml');
		try {
			$codes = Yaml::parse($yaml_file, Yaml::PARSE_OBJECT_FOR_MAP);
		} catch(ParseException $e) {
			error_logs([$e->getMessage(), __LINE__, __FILE__]);
			$codes = NULL;
		} catch(\Exception $e) {
			error_logs([$e->getMessage(), __LINE__, __FILE__]);
			$codes = NULL;
		}
		if(!$codes || !isset($codes->$code)) {
			$response = [
				'message' => 'Internal server error',
				'http_code' => 500,
				'code' => 'APP_INTERNAL_SERVER_ERROR'
			];
		}
		$response = $codes->$code;
		if($data) {
			foreach($data as $key => $value) {
				if(!in_array($key, ['code', 'http_code', 'message', 'meta'])) {
					$response->$key = $value;
				}
			}
		}
		if($options['meta']) {
			$response->meta = [
				'session_id' => IDENTIFIER_UID
			];
		}
		http_response_code($response->http_code);
		die(json_encode($response));
	}
}
?>
