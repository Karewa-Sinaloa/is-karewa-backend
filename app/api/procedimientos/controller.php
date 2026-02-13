<?php
use App\Model\BaseModel;
use App\Model\Crud;

class CProceduresComponent extends BaseModel {

	use Crud;
	
	protected $moduleFields = [
		'id' => ['field' => 'id','readonly' => true, 'saved' => false],
		'name' => ['field' => 'name'],
		'slug' => ['field' => 'slug']
	];

	protected $get_params = [
		'table' => 'c_procedures',
		'filters' => [],
		'joins' => [],
		'search' => []
	];

	protected $rules = [
		'id' => 'required|numeric|unique:admin_units:id',
		'name' => 'required|max:50',
		'slug' => 'required|max:50|unique:c_procedures:slug'
	];

	public function __construct() {
		global $_payload;
		$aditionalPayload = [];
		if($_payload && isset($_payload->name)) {
			$aditionalPayload['slug'] = toAlphanumeric($_payload->name, '-');
		}
		parent::__construct($aditionalPayload);
	}

}
?>
