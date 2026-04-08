<?php
use App\Model\BaseModel;
use App\Model\Crud;

class partidasComponent extends BaseModel {

	use Crud;
	
	protected $moduleFields = [
		'id' => ['field' => 'id','readonly' => true, 'saved' => false],
		'name' => ['field' => 'name'],
		'slug' => ['field' => 'slug']
	];

	protected $get_params = [
		'table' => 'c_partidas',
		'filters' => [],
		'joins' => [],
		'search' => []
	];

	protected $rules = [
		'id' => 'required|numeric|unique:c_partidas:id',
		'name' => 'required|max:50',
		'slug' => 'required|max:50|unique:c_partidas:slug'
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
