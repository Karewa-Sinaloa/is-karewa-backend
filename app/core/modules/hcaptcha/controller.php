<?php
use App\Model\BaseModel;
use App\Model\DBUpdate;
use App\Helpers\ApiResponse;

/**
 * Genera y rota el secreto de bypass de hCaptcha para el administrador
 * autenticado. El texto plano se devuelve una sola vez; en la base de datos
 * solo se guarda su hash.
 */
class HCaptchaBypass extends BaseModel {
	private $db_table = 'users';

	/**
	 * Este endpoint no requiere cuerpo en la petición; se normaliza el payload
	 * vacío para no romper el tipo de la propiedad heredada.
	 */
	public function __construct() {
		global $_payload;
		if (!is_object($_payload)) {
			$_payload = new stdClass();
		}
		parent::__construct();
	}

	public function store() {
		$secret = $this->GenerateSecret();
		ApiResponse::Set('SUCCESS', [
			'data' => [
				'hcaptcha_bypass' => $secret,
			],
		]);
	}

	/**
	 * Crea un secreto aleatorio, guarda su hash para el usuario autenticado
	 * (reemplazando cualquier valor previo) y devuelve el texto plano una vez.
	 */
	public function GenerateSecret() : string {
		$secret  = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
		$fields  = [
			['hcaptcha_bypass', password_hash($secret, PASSWORD_DEFAULT)],
		];
		$filters = [
			['id', USER_ID, '='],
		];
		try {
			DBUpdate::Update($this->db_table, $fields, $filters);
		} catch (\AppException $e) {
			ApiResponse::Set($e->errorCode());
		}
		return $secret;
	}
}
?>
