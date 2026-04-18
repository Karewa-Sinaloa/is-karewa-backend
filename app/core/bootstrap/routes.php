<?php
<<<<<<< HEAD
$routes = [
  'GET' => [
	'users' => 'users',
	'access' => 'access',
	'roles' => 'roles',
	'pages' => 'pages',
	'mailings' => 'mailings',
	'image-upload' => 'image-upload',
	'frontend-logs' => 'frontend-logs',
	'config' => 'config',
	'attachments' => 'attachments',
	'contracts' => 'contracts',
	'estatus-contrato' => 'estatus-contrato',
	'materias' => 'materias',
	'organization' => 'organization',
	'partidas' => 'partidas',
	'periodos-contratos' => 'periodos-contratos',
	'procedimientos' => 'procedimientos',
	'proveedores' => 'proveedores',
	'tipo-contrato' => 'tipo-contrato',
	'unidades-administrativas' => 'unidades-administrativas',
	'unit-types' => 'unit-types',
  ],
  'POST' => [
	'users' => 'users',
	'products' => 'products',
  ],
  'PUT' => [
	'users' => 'users',
	'products' => 'products',
  ],
  'DELETE' => [
	'users' => 'users',
	'products' => 'products',
  ],
];
=======
use App\Helpers\ApiResponse;

try {
	$modules = new moduleManager();
	$modules->routes = [
		'users' => 'users',
		'access' => 'access',
		'roles' => 'roles',
		'pages' => 'pages',
		'mailings' => 'mailings',
		'image-upload' => 'image-upload',
		'frontend-logs' => 'frontend-logs',
		'config' => 'config',
		'attachments' => 'attachments',
		'contracts' => 'contracts',
		'estatus-contrato' => 'estatus-contrato',
		'materias' => 'materias',
		'organization' => 'organization',
		'partidas' => 'partidas',
		'periodos-contratos' => 'periodos-contratos',
		'procedimientos' => 'procedimientos',
		'proveedores' => 'proveedores',
		'tipo-contrato' => 'tipo-contrato',
		'unidades-administrativas' => 'unidades-administrativas',
		'unit-types' => 'unit-types',
	];
	$modules->url_param_module = trim(strip_tags($_GET['m'])) ?? '';
	$module_name = $modules->name();
	define('MODULE', $module_name);
	$module_dir = $modules->path();	
} catch (\AppException $e) {
	ApiResponse::Set($e->getCode());
}
$response = require_once $module_dir . '/index.php';
>>>>>>> master
?>
