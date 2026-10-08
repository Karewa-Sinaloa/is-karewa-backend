<?php
use App\Model\BaseModel;
use App\Model\Crud;

class AppConfig extends BaseModel {

	use Crud;

	protected $moduleFields = [
		'id' => ['field' => 'id', 'saved' => false],
		'name' => ['field' => 'name'],
		'slug' => ['field' => 'slug'],
		'value' => ['field' => 'value'],
		// 1 (privada): solo la ven los roles 1, 2 y 3; 0 (pública): la ve cualquiera.
		// `zero_is_value` permite persistir el 0 en lugar de caer en el default.
		'is_private' => ['field' => 'is_private', 'listed' => true, 'filter' => true, 'saved' => true, 'default' => 1, 'zero_is_value' => true],
		// Permisos de edicion por fila: legible y filtrable, no escribible por API
		// (saved = false); se configura con un UPDATE directo en la base de datos.
		'edit_roles' => ['field' => 'edit_roles', 'listed' => true, 'filter' => true, 'saved' => false]
	];

	protected $get_params = [
		'table' => 'config',
		'filters' => [],
		'joins' => [],
		'search' => ['name', 'slug'],
		'visibility' => ['column' => 'is_private', 'roles' => [1, 2, 3]],
		'edit_roles' => ['column' => 'edit_roles', 'always' => [1]]
	];

	protected $rules = [
		'name' => 'required|max:45',
		'slug' => 'required|max:45|unique:config:slug'
	];
}
?>
