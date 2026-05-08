<?php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once CORE_PATH . 'bootstrap/modules.php';
require_once CORE_PATH . 'helpers/custom_exceptions.php';

function error_logs($data) {
	// This is a placeholder for the actual error logging implementation
	// In a real application, this would log to a file or monitoring system
}

final class RoutesTest extends TestCase
{
	public static function moduleDataProviders() : array {
		return [
			['users'],
			['notexists']
		];
	}

	#[DataProvider('moduleDataProviders')]
	public function testRouting(string $module_test) : void {
		$routes = [
			"users" => "users",
			"roles" => "roles",
			"products" => "products"
		];
		$modules = new moduleManager();	
		$modules->routes = $routes;
		$modules->url_param_module = $module_test;
		try {	
			$module_name = $modules->name();
			$this->assertIsString($module_name);
			$this->assertEquals($routes[$module_test], $module_name);
			$route = $modules->path();
			$this->assertIsString($route);
			$this->assertFileExists($route);
		} catch (\AppException $e) {
			$this->assertInstanceOf(\AppException::class, $e);
		}
	}			
}
?>
