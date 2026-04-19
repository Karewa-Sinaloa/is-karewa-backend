<?php
class AppException extends Exception {
	function __destruct() {
		error_logs([MODULE, 'Message: ' . $this->getMessage(), 'Line: ' . $this->getLine(), 'File: ' . $this->getFile(), 'Code: ' . $this->getCode()]);
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
