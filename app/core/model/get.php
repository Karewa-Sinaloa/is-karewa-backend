<?php
namespace App\Model;
use App\Model\DB;
use PDO;

abstract class DBGet {

  public static function Get(array $params, ?string $action = NULL ) : array|null {
    $limit_inf = 1;
    $table        = is_string($params['table']) ? $params['table'] : null;
    $_filters     = (array_key_exists('filters', $params) && is_array($params['filters'])) ? $params['filters'] : [];
    $_fields      = (array_key_exists('fields', $params) && is_array($params['fields'])) ? $params['fields'] : [];
    $_joins       = (array_key_exists('joins', $params) && is_array($params['joins'])) ? $params['joins'] : [];
    $_page        = (array_key_exists('page', $params) && is_numeric($params['page'])) ? (int) $params['page'] : 1;
    $_max_results = (array_key_exists('max_results', $params) && is_numeric($params['max_results'])) ? (int) $params['max_results'] : 0;
    $_order_by    = (array_key_exists('order', $params) && is_array($params['order'])) ? $params['order'] : [];
    $_group_by    = (array_key_exists('group_by', $params) && is_array($params['group_by'])) ? $params['group_by'] : NULL;
    $_action      = $action;
	
    $joins = self::get_joins($_joins);
	$where = self::get_filters($_filters);

    switch ($action) {
    case 'count':
      $fields  = 'COUNT(*) results';
      $group   = self::get_group_by($_group_by);
			$qry_str = 'SELECT ' . $fields . ' FROM ' . $table . ' ' . $joins . $where . $group;
			break;
    case 'list':
      $fields    = self::getFields($_fields);
      $limits    = self::get_limits($_max_results, $_page);
      $limit_inf = $limits['limit_inf'];
      $order     = self::get_order_by($_order_by);
      $group     = self::get_group_by($_group_by);
      $qry_str   = 'SELECT ' . $fields . ' FROM ' . $table . ' ' . $joins . $where . $group . $order . $limits['limits'];
      break;
    default:
      $fields  = self::getFields($_fields);
      $qry_str = 'SELECT ' . $fields . ' FROM ' . $table . ' ' . $joins . $where . 'LIMIT 1';
      break;
		}
    return self::get_bind_data($_filters, $qry_str, $_max_results, $limit_inf, $_action);
  }

  private static function get_joins(array $joins) {
    $join_qry = '';
    if (!empty($joins)) {
      foreach ($joins as $key => $value) {
        switch ($value['match'][2]) {
        case 'LEFT':
          $join_type = 'LEFT JOIN';
          break;
        case 'RIGHT':
          $join_type = 'RIGHT JOIN';
          break;
        case 'INNER':
          $join_type = 'INNER JOIN';
          break;
        default:
          $join_type = 'LEFT JOIN';
          break;
        }
        $join_qry .= $join_type . ' ' . $value['table'] . ' ON ' . $value['match'][0] . '=' . $value['match'][1] . ' ';
      }
    }
    return $join_qry;
  }

  private static function get_filters(array $filters) {
    $where = '';
    if (count($filters) > 0) {
      $operator = '= :';
      $where    = 'WHERE ';
      $sec      = 1;

      foreach ($filters as $key => $value) {
        if ($value[2] == 'IS_NULL') {
          $operator = 'IS NULL ';
          $wVal     = '';
        }
        /**
         * IN: crea un parametro similar a WHERE `Column Name` IN (`value1`,`value2`...) donde por lo general estos ultimos son numericos, al crear el array se deben agregar de esta manera $filter[['column name', 'value1, value2, ..', 'IN'] ...];
         */
        elseif ($value[2] == 'IN') {
          $operator = 'IN ';
          $wVal     = '';
          $ids      = explode(',', $value[1]);

          foreach ($ids as $key => $id) {
            $wVal .= ':' . preg_replace("/[\.\s\,]+/", '_', $value[0]) . $sec . '_' . $key . ',';
          }
          $wVal = '(' . trim($wVal, ',') . ')';
        }
        /**
         * IN: crea un parametro similar a WHERE `Column Name` IN (`value1`,`value2`...) donde por lo general estos ultimos son numericos, al crear el array se deben agregar de esta manera $filter[['column name', 'value1, value2, ..', 'IN'] ...];
         */
        elseif ($value[2] == 'IN') {
          $operator = ' IN ';
          $wVal     = '';
          $ids      = explode(',', $value[1]);

          foreach ($ids as $key => $id) {
            $wVal .= ':' . preg_replace("/[\.\s\,]+/", '_', $value[0]) . $sec . '_' . $key . ',';
          }
          $wVal = '(' . trim($wVal, ',') . ')';
        } elseif ($value[2] == 'NOT_NULL') {
          $operator = ' IS NOT NULL ';
          $wVal     = '';
        } elseif ($value[2] == 'OR') {
          $wVal = '(';

          foreach ($value[0] as $key => $value_or) {
            $operator = '= :';
            $el       = preg_replace("/[\.\s\,]+/", '_', $value_or) . $sec;
            $wVal .= $value_or . ' ' . $operator . $el . ' || ';
          }
          $wVal = trim($wVal, ' || ');
          $wVal = $wVal . ')';
        } elseif ($value[2] != 'IS_NULL') {
          $operator = $value[2] . ' :';
          $wVal     = preg_replace("/[\.\s\,]+/", '_', $value[0]) . $sec;
        }

        if ($value[2] == 'OR') {
          $where .= $wVal . ' && ';
        } elseif (!empty($value[3])) {
          if ($value[3] == 'CONCAT') {
            $coal   = '';
            $concat = explode(',', $value[0]);

            foreach ($concat as $coalK => $coalV) {
              $coal .= 'COALESCE(' . trim($coalV) . ", ''), ";
            }
            $coal = trim($coal, ', ');
            $where .= $value[3] . '(' . $coal . ') ' . $operator . $wVal . ' && ';
          } else {
            $where .= $value[3] . '(' . $value[0] . ') ' . $operator . $wVal . ' && ';
          }
        } else {
          $where .= $value[0] . ' ' . $operator . $wVal . ' && ';
        }
        $sec++;
      }
      $where = trim($where, ' && ') . ' ';
    }
    return $where;
  }

  private static function get_group_by($group_by) {
    if ($group_by && !empty($group_by)) {
      $group = 'GROUP BY ';
      foreach ($group_by as $key => $param) {
        $group .= $param . ',';
      }
      $group = trim($group, ',');
      return $group . ' ';
    } else {
      return '';
    }
  }

  private static function getFields(array $fields) {
    if (!empty($fields)) {
      $fields_string = '';
      foreach ($fields as $value) {
        $fields_string .= $value . ', ';
      }
      return trim($fields_string, ', ') . ' ';
    } else {
      return '* ';
    }
  }

  private static function get_limits(int $max_results, int $page) :array {
    $limit_inf = 1;
    $limits    = '';

    if ($max_results > 0) {
      $limit_inf = (int) (($page * $max_results) - $max_results);
      $limits    = 'LIMIT :limitInf, :maxResults';
    }
    return [
      'limits'    => $limits,
      'limit_inf' => (int) $limit_inf,
    ];
  }

  private static function get_order_by(array $order_by) {
    $order = '';
    if (count($order_by) > 0) {
      $order = 'ORDER BY ';

      foreach ($order_by as $key => $value) {
        $order .= $value[0] . ' ' . $value[1] . ', ';
      }

      $order = trim($order, ', ') . ' ';
    }
    return $order;
  }

  private static function get_bind_data(array $filters, string $qry_str, int $max_results, int $limit_inf, ?string $action = NULL) :array|null {
	$dbconn     = DB::connection();
    $db_results = [];
	$pdoparam   = PDO::PARAM_STR;
		$qrySec     = 1;
		try{
			$qry = $dbconn->prepare($qry_str);

			if (!empty($filters)) {
				foreach ($filters as $key => $value) {
					if ($value[2] == 'LIKE') {
						$val  = '%' . $value[1] . '%';
						$wVal = preg_replace("/[\.\s\,]+/", '_', $value[0] . $qrySec);
						$qry->bindParam($wVal, $val, $pdoparam);
					} elseif ($value[2] == 'IS_NULL' || $value[2] == 'NOT_NULL') {
						//
					}
					/**
					 * IN: crea un parametro similar a WHERE `Column Name` IN (`value1`,`value2`...) donde por lo general estos ultimos son numericos, al crear el array se deben agregar de esta manera $filter[['column name', 'value1, value2, ..', 'IN'] ...];
					 */
					elseif ($value[2] == 'IN') {
						$ids = explode(',', $value[1]);
						foreach ($ids as $key => $value_param) {
							$wVal = ':' . preg_replace("/[\.]+/", '_', $value[0] . $qrySec . '_' . $key);
							$qry->bindValue($wVal, $value_param, $pdoparam);
						}
					} elseif ($value[2] == 'OR') {
						$o = 1;
						foreach ($value[0] as $key => $value_or) {
							$wVal = ':' . preg_replace("/[\.]+/", '_', $value_or . $qrySec);
							$qry->bindParam($wVal, $value[1][$key], $pdoparam);
							$o++;
						}
					} else {
						$wVal = ':' . preg_replace("/[\.]+/", '_', $value[0] . $qrySec);
						$qry->bindParam($wVal, $value[1], $pdoparam);
					}
					$qrySec++;
				}
			}
			if ($max_results > 0 && $action == 'list') {
				$qry->bindParam(':limitInf', $limit_inf, PDO::PARAM_INT);
				$qry->bindParam(':maxResults', $max_results, PDO::PARAM_INT);
			}
			$qry->execute();
			if ($action == 'list') {
				while ($row = $qry->fetch(PDO::FETCH_ASSOC)) {
					$db_results[] = $row;
				}
			} else {
				$db_results = $qry->fetch(PDO::FETCH_ASSOC);
			}
		} catch(\Exception $e) {
			throw new \AppException($e->getMessage(), 902000);
    }
    if ($db_results === false || $db_results === NULL || empty($db_results) || $db_results == [] || count( (array) $db_results) == 0) {
      return NULL;
    }
    return $db_results;
  }
}
?>
