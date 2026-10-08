<?php

namespace App\Auth;

require_once __DIR__ . '/jwt_token.php';
require_once __DIR__ . '/../helpers/session_manager.php';
use App\Auth\jwtToken;
use Ramsey\Uuid\Uuid;
use App\Helpers\ApiResponse;

abstract class SessionSet
{
    /**
     * Genera un nuevo Token cuando se realiza el login y lo devuelve en la respuesta con los datos necesarios
     * @param  array  $session_data Datos del usuario
     * @return array  Datos de inicio de session junto al Token
     */
    public static function Login(array $session_data, bool $keep_session = false)
    {
        $jwt = null;
        try {
            $jwt = jwtToken::encode($session_data, $keep_session);
        } catch (\AppException $e) {
            ApiResponse::Set($e->errorCode());
        }

        // Registra esta como la única sesión activa del usuario; cualquier
        // sesión previa queda superseded por la última escritura.
        \SessionManager::replace(
            (int) $session_data['id'],
            $jwt->jti,
            time(),
            (int) $jwt->expiration
        );

        if (!defined('USER_ROLE')) {
            define('USER_ROLE', $session_data['role_id']);
        }

        return [
            'access_token' => $jwt->token,
            'expires_in'   => (int) $jwt->expiration,
            'role_id'      => (int) $session_data['role_id'],
            'name'         => $session_data['first_name'],
            'last_name'    => $session_data['last_name'],
            'email'        => $session_data['email'],
            'user_id'      => (int) $session_data['id'],
        ];
    }

    public static function Validate(?string $access_token = null): bool
    {
        $access_granted = false;

        try {
            $jwt_validation = jwtToken::decode($access_token);
        } catch (\AppException $e) {
            ApiResponse::Set($e->errorCode());
        }

        $access_granted = false;
        if ($jwt_validation->status) {
            $token_data = $jwt_validation->token_data;
            $jti        = $token_data->jti ?? null;
            $user_id    = (int) $token_data->data->id;

            // Un token sin jti (emitido antes de esta función) o que no sea la
            // sesión activa, o que esté en la lista negra, es sospechoso: se
            // cancela la sesión activa y se pone el token presentado en la
            // lista negra antes de rechazar la petición.
            $blacklisted = is_string($jti) && $jti !== '' && \SessionManager::isBlacklisted($jti);
            $is_active   = is_string($jti) && $jti !== '' && \SessionManager::isActive($user_id, $jti);

            if (!$is_active || $blacklisted) {
                self::RejectSuspiciousToken($user_id, $jti, (int) $token_data->exp);
            }

            $access_granted = true;
            self::applySession($token_data);
        } else {
            if (!defined('USER_ID')) {
                define('USER_ID', IDENTIFIER_UID);
            }
        }
        return $access_granted;
    }

    /**
     * Expose los datos de la sesión de un token ya validado como constantes,
     * la primera vez que se ven en la petición.
     */
    private static function applySession(object $token_data): void
    {
        $jti = $token_data->jti ?? null;

        if (!defined('USER_ROLE')) {
            define('USER_ROLE', $token_data->data->role_id);
        }
        if (!defined('USER_ID')) {
            define('USER_ID', $token_data->data->id);
        }
        if (!defined('USER_NAME')) {
            define('USER_NAME', $token_data->data->first_name);
        }
        if (!defined('USER_LASTNAME')) {
            define('USER_LASTNAME', $token_data->data->last_name);
        }
        if (!defined('USER_EMAIL')) {
            define('USER_EMAIL', $token_data->data->email);
        }
        if (!defined('EXPIRATION')) {
            define('EXPIRATION', $token_data->exp);
        }
        if (is_string($jti) && $jti !== '' && !defined('USER_JTI')) {
            define('USER_JTI', $jti);
        }
    }

    /**
     * Validación que nunca termina la petición, para los métodos que no exigen
     * token pero sí quieren exponer el rol de quien lo presenta (p. ej. el
     * listado anónimo de config, donde ese rol decide qué filas se ven).
     *
     * Si el token falta, expiró, está mal formado o su sesión ya no es la
     * activa, devuelve false: el método corre como anónimo y no se devuelve
     * ninguna respuesta de autenticación. A diferencia de Validate(), tampoco
     * cancela ni pone en la lista negra la sesión presentada.
     */
    public static function ValidateOptional(?string $access_token = null): bool
    {
        if (!is_string($access_token) || $access_token === '') {
            return false;
        }

        try {
            $jwt_validation = jwtToken::decode($access_token);
        } catch (\AppException $e) {
            return false;
        }

        if (empty($jwt_validation->status) || !is_object($jwt_validation->token_data)) {
            return false;
        }

        $token_data = $jwt_validation->token_data;
        $jti        = $token_data->jti ?? null;
        $user_id    = (int) ($token_data->data->id ?? 0);

        $blacklisted = is_string($jti) && $jti !== '' && \SessionManager::isBlacklisted($jti);
        $is_active   = is_string($jti) && $jti !== '' && \SessionManager::isActive($user_id, $jti);

        if (!$is_active || $blacklisted) {
            return false;
        }

        self::applySession($token_data);
        return true;
    }

    /**
     * Reacciona ante un token no activo, superseded o en la lista negra:
     * cancela la sesión activa del usuario y registra el token presentado.
     * Termina la petición con APP_AUTH_SESSION_REVOKED (901008).
     */
    private static function RejectSuspiciousToken(int $user_id, ?string $jti, int $exp): void
    {
        try {
            if ($user_id > 0) {
                \SessionManager::cancel($user_id);
            }
            if (is_string($jti) && $jti !== '') {
                \SessionManager::blacklist($jti, $user_id, $exp, 'superseded');
            }
        } catch (\AppException $e) {
            ApiResponse::Set($e->errorCode());
        }

        ApiResponse::Set(901008);
    }

    public static function UniqueIdentifierId()
    {
        $headers = apache_request_headers();
        if (!$headers['x-identifier'] || !isset($headers['x-identifier']) || $headers['x-identifier'] == 'undefined') {
            $uid = Uuid::uuid4();
            header('x-identifier:' . $uid);
        } else {
            $uid = $headers['x-identifier'];
        }
        define('IDENTIFIER_UID', $uid);
    }
}
