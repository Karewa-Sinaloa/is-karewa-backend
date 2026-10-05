<?php
use App\Auth\ModuleHandler;

require_once __DIR__ . '/controller.php';
$hcaptcha = new HCaptchaBypass();

$accepted_methods = [
  'store' => [true, [1]],
];
ModuleHandler::Validate($accepted_methods, $hcaptcha);
?>
