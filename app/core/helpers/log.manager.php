<?php

/**
 * [error_logs Guarda los errores en el archivo definido para este fin]
 * @param  array $data Array de datos que se guardarán en el archivo
 * @param  string $file Archivo y dirección donde se guardaran los errores
 * @param [string] $d Datos del array concatenados en un string
 * @param [date time] Fecha en que se genero el login
 * @param [string] IP desde la que se genero la peticion
 */
function error_logs(array $data, string $file = DEBUG_LOG_FILE): void
{
    if (!file_exists($file)) {
        if (!is_dir(DEBUG_LOG_PATH)) {
            mkdir(DEBUG_LOG_PATH, 0777);
        }
        $lfile = fopen($file, 'w') or die('Cannot open file:  ' . $file);
        fclose($lfile);
        chmod($lfile, 0777);
    }
    $user = (defined('USER_ID') && defined('USER_ROLE') && is_numeric(USER_ROLE) && USER_ID > 0) ? USER_ID : (defined('IDENTIFIER_UID') ? IDENTIFIER_UID : 'Guest');
    $d    = '';
    $ip   = $_SERVER['REMOTE_ADDR'];
    $date = date('d-m-Y H:i:s');
    foreach ($data as $value) {
        $d .= $value . ' - ';
    }
    $d = trim($d, ' - ');
    if (error_log('[' . $date . '] - [' . $ip . '] - [' . $user . '] - ' . $d . "\n", 3, $file)) {
        return;
    } else {
        http_response_code(500);
        die(json_encode([
            'message' => 'Internal server error, can not write to log file ' . $file,
            'http_code' => 500,
            'code' => 'APP_INTERNAL_SERVER_ERROR',
        ]));
    }
}
