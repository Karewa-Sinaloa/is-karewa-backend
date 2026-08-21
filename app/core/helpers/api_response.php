<?php

namespace App\Helpers;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

abstract class ApiResponse
{
    public static function Set(string $code, ?array $data = null, ?array $response_options = null): void
    {
        $options = $response_options ?? ['meta' => true];
        $yaml_file = file_get_contents(CORE_PATH . 'config/api_codes.yml');
        try {
            $codes = Yaml::parse($yaml_file, Yaml::PARSE_OBJECT_FOR_MAP);
        } catch (ParseException $e) {
            if (!error_logs([$e->getMessage(), __LINE__, __FILE__])) {
                http_response_code(500);
                die(json_encode([
                    'message' => 'Internal server error, can not write to log file ' . ERROR_LOG_FILE,
                    'http_code' => 500,
                    'code' => 'APP_INTERNAL_SERVER_ERROR',
                ]));
            }
            $codes = null;
        } catch (\Exception $e) {
            if (!error_logs([$e->getMessage(), __LINE__, __FILE__])) {
                http_response_code(500);
                die(json_encode([
                    'message' => 'Internal server error, can not write to log file ' . ERROR_LOG_FILE,
                    'http_code' => 500,
                    'code' => 'APP_INTERNAL_SERVER_ERROR',
                ]));
            }
            $codes = null;
        }
        if (!$codes || !isset($codes->$code)) {
            $response = [
                'message' => 'Internal server error',
                'http_code' => 500,
                'code' => 'APP_INTERNAL_SERVER_ERROR',
            ];
        }
        $response = $codes->$code;
        if ($data) {
            foreach ($data as $key => $value) {
                if (!in_array($key, ['code', 'http_code', 'message', 'meta'])) {
                    $response->$key = $value;
                }
            }
        }
        if ($options['meta']) {
            $response->meta = [
                'session_id' => IDENTIFIER_UID,
            ];
        }
        http_response_code($response->http_code);
        die(json_encode($response));
    }
}
