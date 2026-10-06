<?php
namespace App\Model;
use App\Model\DB;
use Exception;
use PDO;

abstract class DBStore {
  private static $bind_array;
  public static function Store(string $table, array $fields) {
    $qry_str = self::post_qry(DB::prefix() . $table, $fields);
    return self::post_bind_data($qry_str);
  }

  private static function post_qry(string $table, array $fields) {
    $action           = 'INSERT INTO ' . $table;
    $db_vals          = ' VALUES';
    $x                = 0;
    $db_columns       = '';
    self::$bind_array = [];

		foreach ($fields as $field) {
      $db_values = '';
      foreach ($field as $key => $value) {
        if ($x == 0) {
          $var = $value[0];
          if (preg_match("/\./", $value[0])) {
            $var = explode('.', $value[0]);
            $var = $var[1];
          }
          $db_columns .= $var . ', ';
        }
        $varName = preg_replace('/([^a-zA-Z0-9]+)/', '_', $value[0]);
        $db_values .= ':' . $varName . '_' . $x . ', ';
        self::$bind_array[] = [$varName . '_' . $x, $value[1]];
      }

      $db_vals .= ' (' . trim($db_values, ', ') . '),';
      $x = $x + 1;
    }

    $db_cols = ' (' . trim($db_columns, ', ') . ')';
    $db_vals = ' ' . trim($db_vals, ', ');

    return $action . $db_cols . $db_vals;
  }

  private static function post_bind_data(string $qry_str) {
		$dbconn   = DB::connection();
		try {
    	$pdo_type = PDO::PARAM_STR;
    	$qrySave  = $dbconn->prepare($qry_str);

    	foreach (self::$bind_array as $key => $value) {
      	$qrySave->bindParam(':' . $value[0], $value[1], $pdo_type);
    	}
			$qrySave->execute();
		} catch(\AppException $e) {
			throw $e;
		} catch(\Exception $e) {
			throw new \AppException($e->getMessage(), 902003);
		}
    return $dbconn->lastInsertId();
  }
}
?>
