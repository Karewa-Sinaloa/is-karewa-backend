<?php
use App\Model\BaseModel;
use App\Validation\FieldsValidator;
use App\Model\Crud;

class ProductDiscountTypes extends BaseModel {

	use Crud;

	protected $moduleFields = [
		'id' => ['field' => 'id', 'saved' => false],
		'name_es' => ['field' => 'name_es'],
		'name_en' => ['field' => 'name_en']
	];

	protected $get_params = [
		'table' => 'product_discount_types',
		'filters' => [],
		'joins' => [],
		'search' => ['name_es', 'name_en']
	];

	protected $rules = [
		'name_es' => 'required|max:50',
		'name_en' => 'max:50',
	];

	public function __construct() {
		parent::__construct();
	}
}
?>
