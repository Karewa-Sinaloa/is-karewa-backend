<?php
use App\Model\BaseModel;
use App\Model\Crud;

class proveedoresComponent extends BaseModel {

	use Crud;
	
	protected $moduleFields = [
		'id' => ['field' => 'id','readonly' => true, 'saved' => false],
		'name' => ['field' => 'name'],
		'shortname' => ['field' => 'shortname'],
		'rfc' => ['field' => 'rfc'],
		'comments' => ['field' => 'comments']
	];

	protected $get_params = [
		'table' => 'proveedores',
		'filters' => [],
		'joins' => [],
		'search' => []
	];

	protected $rules = [
		'id' => 'required|numeric|unique:proveedores:id',
		'name' => 'required|max:100',
		'shortname' => 'required|max:20|unique:proveedores:shortname',
		'rfc'	 => 'required|rfc|max:13|unique:proveedores:rfc',
	];

	public function __construct() {
		parent::__construct();
	}

}
?>
