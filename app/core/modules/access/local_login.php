<?php
use App\Auth\SessionSet;
use App\Model\BaseModel;
use App\Model\DBGet;
use App\Model\DBUpdate;
use App\Validation\FieldsValidator;
use App\Helpers\ApiResponse;
use App\Helpers\ApiMailer;
use App\Auth\HashAuth;
use App\Helpers\HCaptcha;

class AppAccess extends BaseModel {
	private $db_table = 'users';
	
	protected $moduleFields = [
		'email'         => ['field' => 'email'],
		'password'      => ['field' => 'password'],
		'recovery_code' => ['field' => 'recovery_code'],
		'recovery_date' => ['field' => 'recovery_datetime'],
		'id'            => ['field' => 'id'],
		'first_name'    => ['field' => 'first_name'],
		'last_name'     => ['field' => 'last_name'],
		'role_id'       => ['field' => 'role_id'],
	];

  /**
   * Inicia la validación del login y devuelve la respuesta correspondiente
   * @return Object Datos de respuesta como el código de error, errores si hay
   * y datos necesarios en caso de respuesta correcta
   */
	public function Login() {
		$fv = new FieldsValidator();
		$validation = $fv->Validation([
			"email" => ["email", $this->payload->email],
			"password"	=> ["password", $this->payload->password],
			"token"	=> ["token", $this->payload->token]
		], [
      'email'    => 'required|email',
			'password' => 'required|min:6',
			'token'	=> 'required'
		]);

		if ($validation) {
			ApiResponse::Set(400000, [
				'errors' => $validation
			]);
		}
		try {
			HCaptcha::Validate($this->payload->token);
		} catch(AppException $e) {
			ApiResponse::Set($e->errorCode());
		}
		$params = [
			'table'   => $this->db_table,
			'fields'  => ['password', 'role_id', 'first_name', 'last_name', 'id'],
			'filters' => [
				['status_id', 1, '='],
				['email', $this->payload->email, '='],
			],
		];

    try {
      $user_data = DBGet::Get($params);
    } catch(\AppException $e) {
      ApiResponse::Set(902000);
    }

    $db_pass = $user_data['password'];
    
    if ($user_data && password_verify($this->payload->password, (string) $db_pass)) {
			$login_data = [
				'id'         => $user_data['id'],
				'first_name' => $user_data['first_name'],
				'last_name'  => $user_data['last_name'],
				'email'      => $this->payload->email,
				'role_id'    => $user_data['role_id'],
			];
			$session_set = SessionSet::Login($login_data, true);
			ApiResponse::Set('SUCCESS', [
				'data' => $session_set
			]);
		} else {
			error_logs([MODULE, 401, 'Authetication failed', __LINE__, __FILE__]);
			ApiResponse::Set(901004);
		}
  }
  /**
   * Inicia la verificación de los datos del POST para proceder a la validación
   * y verificación para enviar el código de reseteo de la contraseña
   */
  public function Recovery() {
		$fv = new FieldsValidator();
		$validation = $fv->Validation([
			"email" => ["email", $this->payload->email]
		], [
      'email' => 'required|email',
    ]);

		if ($validation) {
			ApiResponse::Set(400000, [
				'errors' => $validation
			]);
		}
		$get_params = [
			'table'   => $this->db_table,
			'filters' => [
				['email', $this->payload->email, '='],
				['status_id', 1, '='],
			],
			'fields'  => ['id', 'first_name', 'last_name', 'role_id'],
		];
		try {
			$user_data = DBGet::Get($get_params);
		} catch(\AppException $e) {
			ApiResponse::Set(902000);
		}
		if (!$user_data) {
			ApiResponse::Set(404000);
		}
		/**
		 * [$this->rec_code Se genera un código aleatorio númerico de 5 caracteres para una mayor facilidad de uso, luego se encripta como se hace con una contraseña para ser guardado en la base de datos]
		 */
		$rec_date  = date('Y-m-d H:i:s');
		$user_id   = $user_data['id'];
		$role 		 = $user_data['role_id'];
		$user_name = $user_data['first_name'] . ' ' . $user_data['last_name'];
		$rec_code  = mt_rand(100000, 999999);
		$db_code   = password_hash($rec_code, PASSWORD_DEFAULT);
		$fields    = [
			['recovery_datetime', $rec_date],
			['recovery_code', $db_code],
		];
		$filters = [
			['id', $user_id, '='],
		];
		try {
			$rows_affected = DBUpdate::Update($this->db_table, $fields, $filters);
		} catch (\AppException $e) {
			ApiResponse::Set($e->errorCode());
		}
		if($rows_affected == 0) {
			error_logs([MODULE, 'Recovery code not added to database', 'Code: ' . $db_code, __LINE__, __FILE__]);
			ApiResponse::Set(909000);
		}
		$this->loginMailer($this->payload->email, $user_name, 'Código de recuperación de acceso', $rec_code, $role);
		ApiResponse::Set('SUCCESS');
  }
  /**
   * destruir la sesión actual del usuario
  */
  // TODO: Pendiente de implementar el manejo de tokens para invalidar el token actual
  public function Logout() {
    ApiResponse::Set('SUCCESS', [
      'data' => 'Logged out successfully'
    ]);
  }
  /**
   * realizar la peticion de cambio de contraseña validado por medio del código
   * de 6 dígitos que se envio al correo electrónico y el mismo correo electrónico
   */
	public function Reset() {
		$fv = new FieldsValidator();
		$validation = $fv->Validation([
			'email' => ['email', $this->payload->email],
			'code' => ['code', $this->payload->code],
			'password'	=> ['password', $this->payload->password]
		], [
      'email'    => 'required|email',
      'code'     => 'required|numeric|min_value:100000|max_value:999999',
      'password' => 'required|min:8',
    ]);

		if ($validation) {
			ApiResponse::Set(400000, [
				'errors' => $validation
			]);
		}
		$get_data = [
			'table'   => $this->db_table,
			'fields'  => ['recovery_code', 'recovery_datetime', 'first_name', 'last_name', 'role_id', 'id'],
			'filters' => [
				['email', $this->payload->email, '='],
				['status_id', 1, '='],
			],
    ];
    $data = NULL;
    try {
      $data = DBGet::Get($get_data);
}   catch(\AppException $e) {
      ApiResponse::Set(902000);
    }
		if(!$data) {
			ApiResponse::Set(404000);
		}
		$now             = time();
		$user_id         = $data['id'];
		$expiration_date = strtotime($data['recovery_datetime']) + REC_CODE_TIME;
		$password        = password_hash($this->payload->password, PASSWORD_DEFAULT);

		if(!is_numeric($expiration_date) || $now > $expiration_date) {
			ApiResponse::Set(901006);
		} elseif(!password_verify($this->payload->code, $data['recovery_code'])) {
			ApiResponse::Set(901005);
		}

		$filters = [
			['id', $user_id, '='],
		];
		$fields = [
			['recovery_datetime', NULL],
			['recovery_code', NULL],
			['email_verified', 1],
			['password', $password],
		];
		try {
			$rows_affected = DBUpdate::Update($this->db_table, $fields, $filters);
		} catch (\AppException $e) {
			ApiResponse::Set($e->errorCode());
		}catch (\Exception $e) {
			error_logs([MODULE, $e->getMessage(), __LINE__, __FILE__]);
			ApiResponse::Set(909000);
		}
		if($rows_affected == 0) {
			error_logs([MODULE, 'Password not updated', __FILE__, __LINE__]);
		}
		$login_data = [
			'id'         => $user_id,
			'first_name' => $data['first_name'],
			'last_name'  => $data['last_name'],
			'email'      => $this->payload->email,
			'role_id'    => $data['role_id'],
		];
		$session = SessionSet::Login($login_data);
		ApiResponse::Set('UPDATED', [
			'data' => $session
		]);
	}

	private function loginMailer($email, $name, $subject, $code, $role) {
		global $_apiConfig;
		$mailingAddresses = json_decode($_apiConfig->mailing_addresses);
		$system = $mailingAddresses->system;
		$key = HashAuth::Create([$code]);
		$hash = base64_encode(
			json_encode([
				"email" => $email,
				"code" => $code
			])
		);
		$url = API_URL .'mailings/login-recovery?_key=' .$key .'&code=' .$code . '&recovery_hash=' . $hash . "&role=" .$role;
		$html = file_get_contents($url);
		if(!$html) {
			error_logs([MODULE, 'No template found for ' . $url, __FILE__, __LINE__ ]);
			ApiResponse::Set(909000);
		}
		$params = [
			'from' => ['email' => $system->email, $system->name],
			'to' => [
				['email' => $email, 'name' => $name]
			],
			'subject' => $subject,
			'html_body' => $html,
			'text_body' => 'Hola!'
		];
		try {
			ApiMailer::Send($params);
		} catch(\AppException $e) {
			ApiResponse::Set($e->errorCode());
		}
	}
}
?>
