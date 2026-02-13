<?php
use App\Model\BaseModel;
use App\Model\Crud;

class PagesComponent extends BaseModel {

	use Crud {
		store as private storetrait;
		update as private updatetrait;
	}
	
	protected $moduleFields = [
		'id' => ['field' => 'id', 'saved' => false],
		'title' => ['field' => 'title'],
		'slug' => ['field' => 'slug'],
		'content' => ['field' => 'content'],
		'status_id' => ['field' => 'status_id', 'default' => 1],
		'excerpt' => ['field' => 'excerpt'],
		'thumbnail' => ['field' => 'thumbnail'],
		'tags' => ['field' => 'tags']
	];

	protected $get_params = [
		'table' => 'pages',
		'filters' => [],
		'joins' => [],
		'search' => ['title', 'excerpt', 'slug', 'content', 'tags']
	];

	protected $rules = [
		'title' => 'required|max:255',
		'slug' => 'required|max:255|unique:pages:slug',
		'status_id'	 => 'max_value:1|max:2',
		'thumbnail' => 'json'
	];

	public function __construct() {
		parent::__construct();
	}

	public function store() {
		$this->payload->slug = toAlphanumeric($this->payload->title);
		$this->storetrait();
	}
	
	public function update() {
		$this->payload->slug = toAlphanumeric($this->payload->title);
		$this->updatetrait();
	}

}
?>
