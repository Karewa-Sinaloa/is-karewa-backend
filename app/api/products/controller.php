<?php
use App\Model\BaseModel;
use App\Validation\FieldsValidator;
use App\Model\Crud;

class ProductComponent extends BaseModel {

	use Crud {
		store as private storetrait;
		update as private updatetrait;
	}
	
	protected $moduleFields = [
		'id' => ['field' => 'p.id', 'saved' => false],
		'name_es' => ['field' => 'p.name_es'],
		'name_slug_es' => ['field' => 'p.name_slug_es'],
		'info_es' => ['field' => 'p.info_es'],
		'name_en' => ['field' => 'p.name_en'],
		'name_slug_en' => ['field' => 'p.name_slug_en'],
		'ifo_en' => ['field' => 'p.info_en'],
		'status_id' => ['field' => 'p.status_id', 'default' => 1],
		'excerpt_es' => ['field' => 'p.excerpt_es'],
		'excerpt_en' => ['field' => 'p.excerpt_en'],
		'seo_keywords_es' => ['field' => 'p.seo_keywords_es'],
		'seo_keywords_en' => ['field' => 'p.seo_keywords_en'],
		'seo_description_es' => ['field' => 'p.seo_description_es'],
		'seo_description_en' => ['field' => 'p.seo_description_en'],
		'main_category_id' => ['field' => 'p.main_category_id'],
		'parent_category_id' => ['field' => 'p.parent_category_id'],
		'parent2_category_id' => ['field' => 'p.parent2_category_id'],
		'size_chart_es' => ['field' => 'p.size_chart_es'],
		'size_chart_en' => ['field' => 'p.size_chart_en'],
		'status_name' => ['field' => 'ps.name', 'saved' => false],
		'main_category_name_es' => ['field' => 'pcm.name_es', 'saved' => false],
		'main_category_name_en' => ['field' => 'pcm.name_en', 'saved' => false],
		'parent_category_name_es' => ['field' => 'pc1.name_es', 'saved' => false],
		'parent_category_name_en' => ['field' => 'pc1.name_en', 'saved' => false],
		'parent2_category_name_es' => ['field' => 'pc2.name_es', 'saved' => false],
		'parent2_category_name_en' => ['field' => 'pc2.name_en', 'saved' => false]
	];

	protected $get_params = [
		'table' => 'products p',
		'filters' => [],
		'joins' => [
			[
				"table" => "product_status ps",
				"match" => ["ps.id", "p.status_id"]
			],
			[
				"table" => "product_categories pcm",
				"match" => ["pcm.id", "p.main_category_id"]
			],
			[
				"table" => "product_categories pc1",
				"match" => ["pc1.id", "p.parent_category_id"]
			],
			[
				"table" => "product_categories pc2",
				"match" => ["pc2.id", "p.parent2_category_id"]
			]
		],
		'search' => ['name_es', 'name_en', 'description_en', 'description_en', 'name_slug_en', 'name_slug_es']
	];

	protected $rules = [
		'name_es' => 'required|max:150',
		'name_slug_es' => 'required|max:150|unique:products:name_slug_es',
		'name_slug_en' => 'max:150|unique:products:name_slug_en',
		'status_id'	 => 'max_value:1|max:2|exist:product_status:id',
		'size_chart_es' => 'json',
		'size_chart_en' => 'json',
		'excerpt_es' => 'max:255',
		'excerpt_en' => 'max:255',
		'seo_keywords_es' => 'max:255',
		'seo_keywords_en' => 'max:255',
		'main_category_id' => 'numeric|exist:product_categories:id',
		'parent_category_id' => 'numeric|exist:product_categories:id',
		'parent2_category_id' => 'numeric|exist:product_categories:id'
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
