<?php
use App\Auth\ModuleHandler;

require_once __DIR__ . '/controller.php';
$c_type = new CTypeComponent();

$accepted_methods = [
  'index'   => [false],
  'show'    => [false],
  'store'   => [true, [1, 2, 3]],
  'update'  => [true, [1, 2, 3]],
  'destroy' => [true, [1, 2, 3]]
];
ModuleHandler::Validate($accepted_methods, $c_type);
?>
