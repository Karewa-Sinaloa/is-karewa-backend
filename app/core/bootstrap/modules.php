<?php

class moduleManager {
	public $routes;
	public $url_param_module;
	private $controller_paths = [
		ROOT_PATH . 'api/',
		CORE_PATH . 'modules/',
	];

	public function __construct() {
	}

	public function name() {
		if (!is_array($this->routes) || empty($this->routes)) {
			return false;
		}
		foreach ($this->routes as $key => $route) {
			if (strtolower($key) === $this->url_param_module) {
				return trim(strip_tags($route));
			}
		}
		error_logs(['MISSING MODULE', 404, 'No module found in routes', __LINE__, __FILE__]);
		throw new \AppException('MISSING MODULE: No module found in routes', 900001);
	}

	public function path() {
		$module_name = $this->name();
		if (!$this->url_param_module || !is_string($this->url_param_module) || empty($this->url_param_module)) {
			return false;
		}
		foreach ($this->controller_paths as $path) {
			$mdir = $path . $module_name;
			if (is_dir($mdir)) {
				return $mdir;
			}
		}
		error_logs(['MISSING MODULE', 404, 'No module found in routes', __LINE__, __FILE__]);
		throw new \AppException('MISSING MODULE: No module found in routes', 900001);
	}
}
?>
