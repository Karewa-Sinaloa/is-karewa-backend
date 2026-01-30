<?php
use App\Auth\ModuleHandler;

require_once __DIR__ . '/controller.php';
$orgs = new OrganizationComponent();

$accepted_methods = [
  'index'   => [true, [1,2,3]],
  'show'    => [false, NULL],
  'store'   => [true, [1, 2, 3]],
  'update'  => [true, [1, 2, 3]]
];
ModuleHandler::Validate($accepted_methods, $orgs);
?>
