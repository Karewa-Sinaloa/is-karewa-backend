<?php
namespace App\Model;
use App\Model\DB;
use App\Model\DBGet;
use PDO;

abstract class DBDelete {

  public static function delete(string $table, array $filter, ?array $table_assoc = NULL) {
    $filters    = self::delete_filters($filter);
    $qry_delete = 'DELETE FROM ' . MYSQL_PREFIX . $table . $filters;

    if ($table_assoc) {
    	self::is_asociated($table_assoc);
    }
    return self::bind_delete($filter, $qry_delete);
  }

  private static function delete_filters(array $filters) {
    $response  = '';
    $operators = ['=', '>=', '>', '<=', '<', 'LIKE', '!=', '<>'];
    $val_regex = '/([^a-zA-Z0-9]+)/';

    if (count($filters) > 0) {
      $response = 'WHERE ';
      foreach ($filters as $key => $value) {
        $f        = $value[2];
        $operator = '=';
        if (in_array($f, $operators)) {
          $operator = $f;
        }
        $val = preg_replace($val_regex, '_', $value[0]);
        $response .= $value[0] . ' ' . $operator . ' :' . $val . ' && ';
      }
      $response = ' ' . trim($response, ' && ');
    }
    return $response;
  }

  private static function is_asociated(array $table_assoc) {
    if (count($table_assoc) > 0 && is_array($table_assoc)) {
      foreach ($table_assoc as $key => $value) {
        $params = [
          'table'   => $value['table'],
          'filters' => [
            [$value['column'], $value['value'], '='],
          ],
        ];
        $n = DBGet::Get($params, 'count');
        if ($n['results'] > 0) {
          throw new \AppException('This element is associated with another table', 902002);
        }
      }
    }
    return true;
  }

  private static function bind_delete(array $delete_filters, string $qry_delete) {
    $dbconn = DB::connection();
    $qry    = $dbconn->prepare($qry_delete);

    foreach ($delete_filters as $key => $value) {
      $param     = preg_replace('/([^a-zA-Z0-9]+)/', '_', $value[0]);
      $pdo_param = PDO::PARAM_STR;

      if (is_numeric($value[1]) && ctype_digit($value[1])) {
        $pdo_param = PDO::PARAM_INT;
      }

      $qry->bindParam(':' . $param, $value[1], $pdo_param);
    }

		try {
			$qry->execute();
		} catch(\Exception $e) {
			throw new \AppException($e->getMessage(), 902000);
		}
		$qty = $qry->rowCount();
    return $qty;
  }
}
?>
