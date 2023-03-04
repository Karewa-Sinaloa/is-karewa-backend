<?php
use App\Model\BaseModel;
use App\Validation\FieldsValidator;
use App\Model\Crud;

class ProductCategories extends BaseModel {

	use Crud {
		store as private storetrait;
		update as private updatetrait;
	}
	
	protected $moduleFields = [
		'id' => ['field' => 'pc.id', 'saved' => false],
		'name_es' => ['field' => 'pc.name_es'],
		'name_slug_es' => ['field' => 'pc.name_slug_es'],
		'description_es' => ['field' => 'pc.description_es'],
		'name_en' => ['field' => 'pc.name_en'],
		'name_slug_en' => ['field' => 'pc.name_slug_en'],
		'description_en' => ['field' => 'pc.description_en'],
		'status_id' => ['field' => 'pc.status_id', 'default' => 1],
		'image' => ['field' => 'pc.image'],
		'discount' => ['field' => 'pc.discount'],
		'discount_type_id' => ['field' => 'pc.discount_type_id'],
		'parent_id' => ['field' => 'pc.parent_id'],
		'parent2_id' => ['field' => 'pc.parent2_id'],
		'discount_name_es' => ['field' => 'pcd.name_es', 'saved' => false],
		'discount_name_en' => ['field' => 'pcd.name_en', 'saved' => false],
		'status_name' => ['field' => 'pcs.name', 'saved' => false]
	];

	protected $get_params = [
		'table' => 'product_categories pc',
		'filters' => [],
		'joins' => [
			[
				"table" => "product_status pcs",
				"match" => ["pcs.id", "pc.status_id"]
			],
			[
				"table" => "product_discount_types pcd",
				"match" => ["pcd.id", "pc.discount_type_id"]
			]
		],
		'search' => ['name_es', 'name_en', 'description_en', 'description_en', 'name_slug_en', 'name_slug_es']
	];

	protected $rules = [
		'name_es' => 'required|max:100',
		'name_slug_es' => 'required|max:100|unique:product_categories:name_slug_es',
		'name_slug_en' => 'max:100|unique:product_categories:name_slug_en',
		'status_id'	 => 'max_value:1|max:2|exist:product_status:id',
		'image' => 'json',
		'discount' => 'numeric',
		'discount_type_id' => 'numeric|exist:product_discount_types:id',
		'parent_id' => 'numeric|exist:product_categories:id',
		'parent2_id' => 'numeric|exist:product_categories:id'
	];

	public function __construct() {
		parent::__construct();
	}

	public function store() {
		$this->payload->name_slug_es = toAlphanumeric($this->payload->name_es);
		$this->payload->name_slug_en = toAlphanumeric($this->payload->name_en);
		$this->storetrait();
	}
	
	public function update() {
		$this->payload->name_slug_es = toAlphanumeric($this->payload->name_es);
		$this->payload->name_slug_en = toAlphanumeric($this->payload->name_en);
		$this->updatetrait();
	}

}
?>
