<?php
use App\Model\BaseModel;

class AppConfig extends BaseModel {
	
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

  public function show() {
		parent::get();
  }

	public function index() {
		parent::get();
  }

  public function store() {
		parent::post();
  }

  public function update() {
		parent::put();
  }

  public function destroy() {
    $filters = [
      ['id', $id, '='],
    ];
    $table_assoc = [
      [
        'table'  => 'users',
        'column' => 'role_id',
        'value'  => $id,
      ],
    ];
    return baseModel::delete($this->db_table, $filters, $table_assoc);
  }
}
?>
