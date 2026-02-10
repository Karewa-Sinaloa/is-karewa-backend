<?php
use App\Model\BaseModel;
use App\Model\Crud;

class organizationComponent extends BaseModel {

	use Crud;
	
	protected $moduleFields = [
		'id' => ['field' => 'id', 'default' => 1, 'readonly' => true, 'saved' => false],
		'name' => ['field' => 'name'],
		'shortname' => ['field' => 'shortname'],
		'contact_email' => ['field' => 'contact_email'],
		'street' => ['field' => 'street'],
		'colonia' => ['field' => 'colonia'],
		'city' => ['field' => 'city'],
		'state' => ['field' => 'state'],
		'postal_code' => ['field' => 'postal_code'],
	];

	protected $get_params = [
		'table' => 'organization',
		'filters' => [],
		'joins' => [],
		'search' => []
	];

	protected $rules = [
		'id' => 'required|numeric|unique:organization:id',
		'name' => 'required|max:100',
		'shortname' => 'required|max:20',
		'contact_email'	 => 'required|email|max:100',
		'street' => 'max:150',
		'colonia' => 'max:150',
		'city' => 'max:100',
		'state' => 'max:100',
		'postal_code' => 'numeric|max:5',
	];

	public function __construct() {
		parent::__construct();
	}

}
?>
