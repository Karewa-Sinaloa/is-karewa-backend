<?php
use App\Auth\ModuleHandler;

require_once __DIR__ . '/controller.php';
$prov = new ProveedoresComponent();

$accepted_methods = [
  'index'   => [false],
  'show'    => [false],
  'store'   => [true, [1, 2, 3]],
  'update'  => [true, [1, 2, 3]],
  'destroy' => [true, [1, 2, 3]]
];
ModuleHandler::Validate($accepted_methods, $prov);
?>
