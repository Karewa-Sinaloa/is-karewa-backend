<?php
use App\Model\BaseModel;
use App\Validation\FieldsValidator;
use App\Model\Crud;

class ProductStatus extends BaseModel {

	use Crud;

	protected $moduleFields = [
		'id' => ['field' => 'id', 'saved' => false],
		'name' => ['field' => 'name']
	];

	protected $get_params = [
		'table' => 'product_status',
		'filters' => [],
		'joins' => [],
		'search' => ['name']
	];

	protected $rules = [
		'name' => 'required|max:50'
	];

	public function __construct() {
		parent::__construct();
	}
}
?>
