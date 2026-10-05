<?php
use App\Model\BaseModel;
use App\Model\Crud;

class AppConfig extends BaseModel {

	use Crud;

	protected $moduleFields = [
		'id' => ['field' => 'id', 'saved' => false],
		'name' => ['field' => 'name'],
		'slug' => ['field' => 'slug'],
		'data' => ['field' => 'data'],
		'public' => ['field' => 'public', 'default' => 0, 'listed' => false]
	];

	protected $get_params = [
		'table' => 'config',
		'filters' => [],
		'joins' => [],
		'search' => ['name', 'public', 'slug']
	];

	protected $rules = [
		'name' => 'required|max:45',
		'slug' => 'required|max:45|unique:config:slug',
		'public'	 => 'max_value:1|max:1'
	];
}
?>
