<?php
namespace App\Validation;
use App\Model\DBGet;

class FieldsValidator {
	  /**
   * Valida los campos de un request con base en las reglas proporcionadas.
   *
   * @param  array	 $request Array asociativo que contiene los campos a validar.
   * @param  array	 $rules	 Array asociativo que contiene las reglas de validación para cada campo.
   * @param  int|null $id		 ID opcional para validaciones específicas (por ejemplo, para ignorar un registro existente).
   * @return array|false		 Retorna un array con los errores de validación o false si no hay errores.
   */

  public function Validation(Array $request, array $rules = [], ?int $id = NULL): array|false {
	if(count($rules) == 0){
		return false;
	}
	$response = [];
	foreach ($rules as $field => $rule) {
		$field_rules = explode('|', $rule);
		if (array_key_exists($field, $request)) {
			$rField = $request[$field][1];
			if (!empty($rField)) {
				foreach ($field_rules as $value) {
					$rule_data	 = explode(':', $value);
					$rule_name	 = $rule_data[0];
					$rule_method = $rule_name;
					if ($rule_name !== 'required') {
						if ($rule_name === 'unique' && method_exists($this, $rule_method)) {
							$index_field = 'id';
							$result		 = $this->$rule_method($rule_data[1], $rule_data[2], $rField, $id, $index_field);
							if (!empty($result)) {
								$response[$field][$rule_name] = $result;
							}
						} elseif ($rule_name === 'exist' && method_exists($this, $rule_method)) {
							$result = $this->$rule_method($rField, $rule_data[1], $rule_data[2]);
							if (!empty($result)) {
								$response[$field][$rule_name] = $result;
							}
						} elseif (isset($rule_data[1]) && !empty($rule_data[1]) && method_exists($this, $rule_method)) {
							$result = $this->$rule_method($rule_data[1], $rField);
							if (!empty($result)) {
								$response[$field][$rule_name] = $result;
							}
						} else if (method_exists($this, $rule_method)) {
							$result = $this->$rule_method($rField);
							if (!empty($result)) {
								$response[$field][$rule_name] = $result;
							}
						} else {
							$response[$field]['error'] = "Validation method named`{$rule_method}` was not found";
						}
					}
				}
			} elseif (in_array('required', $field_rules) && (empty($rField) || !$rField)) {
				$response[$field]['required'] = 'Field required';
			}
		}
	}

	if (count($response) > 0) {
	  return $response;
	} else {
	  return false;
	}
  }

  private function max($value, $var): string|null {
	$response = null;
	if (strlen($var) > $value) {
	  $response = 'Maximum length excceded';
	}
	return $response;
  }

  private function min($value, $var) {
	if (strlen($var) < $value) {
	  $response = 'Minimum length excceded';
	}
	return $response;
  }

  private function alpha_num($var) {
	$regex = '/([^a-zA-Z0-9áéíóúÁÉÓÚÑñüÜ]+)/';
	if (preg_match($regex, $var)) {
	  $response = 'Field must have only alphabetic caracters with no spaces';
	}
	return $response;
  }

  private function alpha_dash($var) {
	$regex = "/([^a-zA-Z0-9áéíóúÁÉÓÚÑñüÜ\_\-]+)/";
	if (preg_match($regex, $var)) {
	  $response = 'Field must only cointain numbers, letters, dashes or/and underscores';
	}
	return $response;
  }

  private function alpha($var) {
	$regex = '/([^a-zA-ZáéíóúÁÉÓÚÑñüÜ]+)/';
	if (preg_match($regex, $var)) {
	  $response = 'Field must have only alphabetic caracters with no spaces';
	}
	return $response;
  }

  private function base64($var) {
	$regex = "/([^a-zA-Z0-9\/\+\=]+)/";
	if (preg_match($regex, $var)) {
	  $response = 'Field seems not to be a Base64 String';
	}
	return $response;
  }

  private function alpha_spaces($var) {
	$regex = "/([^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+)/";
	if (preg_match($regex, $var)) {
	  $response = 'Field must have only alphabetic caracters and spaces';
	}
	return $response;
  }

  private function numeric($var) {
	if (!is_numeric($var)) {
	  $response = 'Field must be numeric';
	}
	return $response;
  }

  private function max_value($value, $var) {
	if ($var > $value) {
	  $response = 'Maximum value excceded';
	}
	return $response;
  }

  private function min_value($value, $var) {
	if ($var < $value) {
	  $response = 'Minimum value excceded';
	}
	return $response;
  }

  private function email($var) {
	if (filter_var($var, FILTER_VALIDATE_EMAIL) === false) {
	  $response = 'Invalid email format';
	}
	return $response;
  }
  /**
   * Verifica en la base de datos si el valor de una colunma es unico o no.
   * @param  string $table		 table name
   * @param  string $field		 Column name of the value to be found
   * @param  string $value		 Value to be found
   * @param  int	$id			 ID to be ignored if it is an existing row, it helps in case of updating a value
   * @param  string $index_field Name of the column that is going to act ID to be ignored
   * @return string				 Return a String if the value is not unique
   */
  private function unique(string $table, string $field, string $value, int $id = null, string $index_field = ''): string {
	$filter   = [];
	$response = '';
	if ($id || $id != 0) {
	  $filter[] = [$index_field, $id, '<>'];
	}
	$filter[] = [$field, $value, '='];
	$filter[] = [$field, NULL, 'NOT_NULL'];
	$get_data = [
	  'table'	=> $table,
	  'filters' => $filter,
	];
	$n	  = DBGet::Get($get_data, 'count');
	$rows = $n['results'];
	if ($rows > 0) {
	  $response = 'Column value not unique';
	}
	return $response;
  }

  private function date_format($date) {
	$date = explode('-', $date);
	if (!checkdate($date[1], $date[2], $date[0])) {
	  $response = 'Date format is incorrect';
	}
	return $response;
  }

  private function time_format($value) {
	$reg = preg_match("/^([0-1][0-9]|2[0-3])((\:[0-5][0-9]){2})$/", $value);
	if (!$reg) {
	  $response = 'Time format incorrect';
	}
	return $response;
  }

  private function exist($id, string $table, string $row) {
	$get_data = [
	  'table'	=> $table,
	  'filters' => [
		[$row, $id, '='],
	  ],
	];
	$n	  = DBGet::Get($get_data, 'count');
	$rows = $n['results'];
	if ($rows == 0) {
	  $response = 'Row does not exist';
	}
	return $response;
  }

  private function decimal(string $number) {
	$response = false;
	$regex	  = "[^0-9\.]";
	if (preg_match('#' . $regex . '#', $number)) {
	  $response = 'Invalid decimal format';
	}
	return $response;
  }

  private function rfc(string $rfc) {
	$regex = '/^([a-zA-Z]{3,4})([0-9]{2})([0][1-9]|[1][0-2])([0][1-9]|[1-2][0-9]|[3][0-2])([a-zA-Z0-9]{3})$/';
	if (!preg_match($regex, $rfc)) {
	  $response = 'RFC Format incorrect';
	}
	return $response;
  }

  private function url(string $url) {
	if (!filter_var($url, FILTER_VALIDATE_URL)) {
	  $response = 'URL Format incorrect';
	}
	return $response;
  }

  private function boolean($value) {
	$response = false;
	if ($value == true || $value == 1) {
	  $response = false;
	} else {
	  $response = 'Value is not boolean';
	}
	return $response;
  }

  private function json($json) {
	$response = false;
	if (json_decode($json) === null) {
	  $response = 'Value is not a JSON string';
	}
	return $response;
  }
}
?>
