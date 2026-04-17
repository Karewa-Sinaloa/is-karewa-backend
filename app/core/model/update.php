<?php
namespace App\Model;
use App\Model\DB;
use PDO;

abstract class DBUpdate {

  private static $index;

  public static function Update(string $table, array $_fields, array $_filters, array $_joins = []) {
    self::$index = 0;

    $fields  = self::put_fields($_fields);
		$filters = self::put_filters($_filters);
    $join    = self::put_join($_joins);

		$qry_str = 'UPDATE ' . MYSQL_PREFIX . $table . ' ' . $join . 'SET ' . $fields . $filters;
    return self::put_bind_data($_fields, $_filters, $qry_str);
  }

  private static function put_join(array $joins) {
    $join = '';
    if (count($joins) > 0) {
      foreach ($joins as $key => $table) {
        $join .= 'JOIN ' . MYSQL_PREFIX . $table['table'] . ' ON ' . $table['match'][0] . ' = ' . $table['match'][1] . ' ';
      }
    }
    return $join;
  }

  private static function put_fields(array $_fields) {
    $fields = '';
    $i      = self::$index;

    if (count($_fields) > 0) {
      foreach ($_fields as $field) {
        $varName = preg_replace('/([^a-zA-Z0-9]+)/', '_', $field[0]);
        if ($field[2] == 'increase') {
          $fields .= $field[0] . '= ' . $field[0] . ' + :' . $varName . '_' . $i . ', ';
        } elseif ($field[2] == 'decrease') {
          $fields .= $field[0] . '= ' . $field[0] . ' - :' . $varName . '_' . $i . ', ';
        } else {
          $fields .= $field[0] . '=:' . $varName . '_' . $i . ', ';
        }
        $i++;
      }
			$fields = trim($fields, ', ') . ' ';
			self::$index = $i;
    	return $fields;
    } else {
			throw new \AppException('No fields to update given', 902001);
    }
  }

  private static function put_filters(array $filter) {
    $filters = '';
    $i       = self::$index;

    if (count($filter) > 0) {
      $filters = 'WHERE ';
      foreach ($filter as $key => $_filter) {
        $f = $_filter[2];
        if ($f == '=' || $f == '>=' || $f == '>' || $f == '<=' || $f == '<' || $f == 'LIKE' || $f == '!=' || $f == '<>') {
          /**
           * [$varName crea los parametros unicos de los filtros a partir
           * de los nombres de las columnas que se envian en el array de
           * filtros, reeplazando todo caracter no alfanumerico por guiones
           * bajos]
           * @var [type]
           */
          $varName = preg_replace('/([^a-zA-Z0-9]+)/', '_', $_filter[0]);

          switch ($f) {
          case 'LIKE':
            $match = ' LIKE %:' . $varName . '_' . $i . '%';
            break;
          default:
            $match = $f . ':' . $varName . '_' . $i;
            break;
          }

          $filters .= $_filter[0] . $match . ' AND ';
        }
        $i++;
      }
      $filters = trim($filters, ' AND ') . ' ';
    }
    self::$index = 0;
    return $filters;
  }

  private static function put_bind_data(array $fields, array $filter, string $qry_str) {
		$i         = 0;
    $pdo_type  = PDO::PARAM_STR;
    $dbconn    = DB::connection();
		$bindArray = array_merge($fields, $filter);
		try {
			$q = $dbconn->prepare($qry_str);

			foreach ($bindArray as $key => $value) {
				$varName = preg_replace('/([^a-zA-Z0-9]+)/', '_', $value[0]);
				if (empty($value[1])) {
					$val = NULL;
					$q->bindParam(':' . $varName . '_' . $i, $val, $pdo_type);
				} else {
					$q->bindParam(':' . $varName . '_' . $i, $value[1], $pdo_type);
				}
				$i++;
			}
			$q->execute();
		} catch(\Exception $e) {
			throw new \AppException('Database query error: ' . $e->getMessage(), 902000);
		}
    return $q->rowCount();
  }
}
?>
