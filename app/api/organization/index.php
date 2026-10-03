<?php

use App\Auth\ModuleHandler;

require_once __DIR__ . '/controller.php';
$orgs = new organizationComponent();

$accepted_methods = [
    'index'   => [true, [1,2,3]],
    'show'    => [false, null],
    'store'   => [true, [1, 2, 3]],
    'update'  => [true, [1, 2, 3]],
];
ModuleHandler::Validate($accepted_methods, $orgs);
