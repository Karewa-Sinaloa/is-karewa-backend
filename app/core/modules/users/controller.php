<?php
use App\Auth\SessionSet;
use App\Model\BaseModel;
use App\Model\DBGet;
use App\Model\DBStore;
use App\Validation\FieldsValidator;
use App\Auth\HashAuth;
use App\Helpers\ApiResponse;
use App\Model\Crud;

class Users extends BaseModel {

	use Crud {
		update as protected traitupdate;
		store as protected traitstore;
	}

	protected $moduleFields = [
		'id'                 => ['field' => 'u.id', 'saved' => false],
		'email'              => ['field' => 'u.email'],
		'password'           => ['field' => 'u.password', 'filter' => false, 'listed' => false, 'optional' => true],
		'status_id'          => ['field' => 'u.status_id', 'default' => 1],
		'status_name'        => ['field' => 's.name', 'saved' => false],
		'role_id'            => ['field' => 'u.role_id', 'default' => 5],
		'role_name'          => ['field' => 'r.name', 'saved' => false],
		'first_name'         => ['field' => 'u.first_name'],
		'last_name'          => ['field' => 'u.last_name'],
		'recovery_code'      => ['field' => 'u.recovery_code', 'filter' => false, 'listed' => false, 'optional' => true],
		'recovery_date'      => ['field' => 'u.recovery_date'],
		'phone'              => ['field' => 'u.phone'],
		'phone_verified'     => ['field' => 'u.phone_verified'],
		'middle_name'        => ['field' => 'u.middle_name'],
		'second_last_name'   => ['field' => 'u.second_last_name'],
		'hcaptcha_bypass'    => ['field' => 'u.hcaptcha_bypass', 'filter' => false, 'listed' => false, 'saved' => false],
	];

	protected $get_params = [
		'table'   =>'users u',
		'filters' => [],
		'joins'   => [
			[
				'table' => 'roles r',
				'match' => ['r.id', 'u.role_id'],
			],
			[
				'table' => 'users_status s',
				'match' => ['s.id', 'u.status_id'],
			],
		],
		'search' => ['u.first_name', 'u.last_name', 'u.email', 'u.phone', 'r.name']
	];

	protected $rules = [
		'email'              => 'required|unique:users:email|email',
		'status_id'          => 'numeric|min_value:1|max:4|exist:users_status:id',
		'role_id'            => 'numeric|min_value:1|max:4|exist:roles:id',
		'first_name'         => 'required|min:3|max:100',
		'last_name'          => 'required|min:3|max:100',
		'phone'              => 'min:10|max:20|numeric|unique:users:phone',
		'phone_verified'     => 'boolean',
		'dob'                => 'date_format',
		'password'           => 'min:6',
	];

	private $id = NULL;

	function __construct() {
		parent::__construct();
		$this->id = $_GET['id'];
		if (defined('USER_ROLE') && USER_ROLE > 3) {
      $this->get_params['filters']['id'] = ['u.id', USER_ID, '='];
		}
		$this->table_assoc = [
			[
				'table' => 'customers',
				'column' => 'user_id',
				'value' => $id
			]
		];
		if(in_array(REQUEST_TYPE, ['update', 'destroy'])) {
			// Solo puede cambiar datos de otros usuarios que tengan un rol menor al suyo
			if(defined('USER_ROLE') && USER_ROLE <= 3) {
				$this->get_params['filters'] = [
					'role_id' => ['u.role_id', USER_ROLE, '>='],
				];
			}
		}
	}

  public function store() {
    // Si no se esta logueado el rol predefinido es 5
    if (!defined('USER_ROLE') || USER_ROLE > 3) {
      $this->payload->role_id = 5;
    }
    // Si el rol a asignar es mayor al del usuario que asigna se cambia por el mismo del usuario asignante para que no pueda escalar privilegios, asi por ejemplo un administrador puede crear otro administrador pero un manager no puede crear un administrador
    elseif (defined('USER_ROLE') && $this->payload->role_id < USER_ROLE) {
      $this->payload->role_id = USER_ROLE;
    }

    $this->rules['password'] = 'required|min:8';
		$pHash = password_hash($this->payload->password, PASSWORD_DEFAULT);
		$this->queryFields = [
			'password' => ['u.password', $pHash]
		];
		$end = defined('USER_ROLE') && USER_ROLE <= 3 ? true : false;

		$this->methodOptions['end'] = $end;

		$result = $this->traitstore();
		$customer_qry_fields[] = [
			'user_id' => ['user_id', $result],
			'dob' => ['dob', $this->payload->dob],
			'lang_id' => ['lang_id', 1]
		];
		try {
			$last_stored = DBStore::Store('customers', $customer_qry_fields);
		} catch(\AppException $e) {
			ApiResponse::Set($e->errorCode());
		}
		if (!$last_stored || $last_stored == 0) {
			error_logs([MODULE, 'Customer could not be created', 	__LINE__, __FILE__]);
			ApiResponse::Set(904000);
		}
		$login_data = [
			'id'         => $result,
			'first_name' => $this->payload->first_name,
			'last_name'  => $this->payload->last_name,
			'email'      => $this->payload->email,
			'role_id'    => $this->payload->role_id
		];
		$session = SessionSet::Login($login_data);
		ApiResponse::Set('SUCCESS', [
			'data' => $session
		]);
  }

	public function update() {
		if ($this->payload->password && !empty($this->payload->password)) {
			$this->payload->password = password_hash($this->payload->password, PASSWORD_DEFAULT);
		}
		$this->traitupdate();
  }

	private function mailingRegistration() {
		try {
			$req = [
				'email_address' => $this->payload->email,
				'status' => 'subscribed',
				'merge_fields' => [
					'FNAME' => $this->payload->first_name,
					'LNAME' => $this->payload->last_name
				]
			];
			$response = MailChimp::createContact($req);
		} catch (\Exception $th) {
      error_logs(['Error saving new user to mailing list: ', $th->getMessage(), $th->getCode]);
		}
	}

	private function welcomeMailing($user) {
		$translation = [
			'es' => [
				'welcome' => 'Bienvenida',
				'alt' => 'Si no puedes visualizar este mensaje ingresa aquí: '
			],
			'en' => [
				'welcome' => 'Welcome',
				'alt' => 'If this message is not visible, please follow this link: '
			]
		];
		$transl = $translation[$user['lang']];
		$hash = HashAuth::Create($user['id']);
		$url = MAILINGS_URL .'/registration/' .$user['id'] .'/' .$hash;
		$html = file_get_contents($url);
		$data = [
			'to' => [
				[$user['email'], $user['first_name'] .' ' . $user['last_name']]
			],
			'cco'	=> [
				$GLOBALS['contacts']['developer'],
				$GLOBALS['contacts']['sales'],
				$GLOBALS['contacts']['support']
			],
			'reply' => $GLOBALS['contacts']['sales'],
			'subject' => $transl['welcome'] . ': ' .$user['first_name'] .' ' . $user['last_name'],
			'alt' => $transl['alt'] .$url,
			'body' => $html
		];
		try {
			$mailing = Mailer::send($data);
		} catch(\Exception $e) {
      error_logs(['Error sending welcome message: ', $e->getMessage(), $e->statusCode]);
		}
    error_logs(['NOTIFICATION', 'Welcome mailing confirmation sent', 'User: ' .$user['id']]);
	}
}
?>
