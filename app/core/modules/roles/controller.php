<?php
use App\Model\BaseModel;
use App\Model\Crud;

class Roles extends BaseModel{

	use Crud;

	protected $moduleFields = [
		'id'   => ['field' => 'id', 'saved' => false],
		'name' => ['field' => 'name'],
	];

	protected $get_params = [
		'table' => 'user_roles',
		'search' => ['name']
	];

	protected $rules = [
    'name'   => 'required|max:45|min:4|alpha',
    'status' => 'required|numeric|min_value:1|max_value:99',
	];
	
	function __construct() {
		global $_payload;
		parent::__construct($_payload);
		$this->table_assoc = [
			[
				'table'  => 'users',
				'column' => 'role_id',
				'value'  => $_GET['id']
		]
		];
	}
}
?>
