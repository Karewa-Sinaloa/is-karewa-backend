<?php
use PHPUnit\Framework\TestCase;
use App\Validation\FieldsValidator;
require_once __DIR__ . '/../app/core/validation/fields.php';

final class ValidationTest extends TestCase
{
	public function testValidationWithNoRulesReturnsFalse()
	{
		$request = ['name' => ['name', 'John Doe']];
		$rules = [
			"name" => "required|max:150",
			"shortname" => "required|max:20",
			"contact_email" => "required|email|max:100",
			"street" => "max:150",
			"city" => "max:100",
			"colonia" => "max:150",
			"state" => "max:100",
			"zip_code" => "numeric|max:5",
		];
		$clss = new FieldsValidator;
		$result = $clss->Validation($request, $rules);
		$this->assertFalse($result);
	}
}
?>
