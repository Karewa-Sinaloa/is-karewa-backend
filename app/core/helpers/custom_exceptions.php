<?php
class AppException extends Exception {
	function __destruct() {
		$module  = !defined('MODULE') ? 'UNKNOWN MODULE' : MODULE;
		error_logs([$module, 'Message: ' . $this->getMessage(), 'Line: ' . $this->getLine(), 'File: ' . $this->getFile(), 'Code: ' . $this->getCode()]);
	}

	public function errorMessage() : string {
		$errMsg = 'Message: ' . $this->getMessage() . ' on line: ' . $this->getLine() . ' on file: ' . $this->getFile() . ' with code: ' . $this->getCode();
		return $errMsg;
	}

	public function errorCode() : string {
		return $this->getCode();
	}
}
?>
