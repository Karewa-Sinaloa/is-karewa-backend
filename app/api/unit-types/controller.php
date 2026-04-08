<?php
use App\Model\BaseModel;
use App\Model\Crud;

class unitTypesComponent extends BaseModel {

	use Crud;
	
	protected $moduleFields = [
		'id' => ['field' => 'id','readonly' => true, 'saved' => false],
		'name' => ['field' => 'name'],
		'slug' => ['field' => 'slug']
	];

	protected $get_params = [
		'table' => 'unit_types',
		'filters' => [],
		'joins' => [],
		'search' => []
	];

	protected $rules = [
		'id' => 'required|numeric|unique:unit_types:id',
		'name' => 'required|max:50',
		'slug' => 'required|max:50|unique:unit_types:slug'
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
