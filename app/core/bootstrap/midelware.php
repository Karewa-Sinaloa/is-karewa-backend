<?php
namespace App\Model;
use App\Model\DBDelete;
use App\Validation\FieldsValidator;
use App\Model\DBGet;
use App\Model\DBStore;
use App\Model\DBUpdate;
use App\Helpers\ApiResponse;

trait Crud {
  public function show() {
    return parent::get();
  }

  public function index() {
    return parent::get();
  }

  public function store() {
    return parent::post();
  }

  public function update() {
    return parent::put();
  }

  public function destroy() {
    return parent::delete();
  }
}

class BaseModel {
  protected $get_fields;
  protected $search_fields;
  protected $embeded = [];
  protected $availableFields = [];
  protected $entryId = NULL;
  protected array $table_assoc = [];
  protected $queryFields = NULL;
  protected Object $payload;
  protected array $methodOptions = [
    'end' => true
  ];
  protected $get_params = [
    'table'   => null,
    'filters' => [],
    'joins'   => [],
    'search'  => [],
  ];
  protected $rules = [];

  function __construct(array $additionalData = []) {
	global $_payload;
    if($_payload && count($additionalData) > 0) {
	  $this->payload = $_payload;
		foreach ($additionalData as $key => $value) {
		  $this->payload->$key = $value;
		}
    }
  }

  protected function init() {
    $params = (object) $this->get_params;
    $this->controllerFields();
    // Pagina actual de la páginación
    $get_page = $_GET['page'] > 0 ? (int) $_GET['page'] : 1;
    // Maxima cantidad de resultados de busqueda
    $get_max_results = is_numeric($_GET['limit']) ? (int) $_GET['limit'] : 9999999999;
    // Campos que apareceran en la busqueda
    $get_fields = $this->getParamsFields();
    //  Orden solicitado de los resultadospor medio del API
    $get_order_by = $this->getParamsOrder();
    // Agrupacion
    $get_group_by = $this->getParamsGroup();
    if (!empty($params->group)) {
      foreach ($params->group as $key => $param) {
        array_push($get_group_by, $param);
      }
    }
    if (count($get_group_by) == 0) {
      $get_group_by = null;
    }
    // Filtros de busqueda
    $get_filters = $this->getParamsFilters($params->search);
    $this->entryId = trim(strip_tags((string) $_GET['id']));
    if (!empty($params->filters)) {
      foreach ($params->filters as $key => $filter) {
        if($key == 'id') {
          $this->entryId = $filter[1];
        }
        $get_filters[$key] = $filter;
      }
    }
    // Joins solicitados desde el modulo, no se pueden solicitar desde el API
    $get_joins = [];
    if (!empty($params->joins)) {
      foreach ($params->joins as $key => $join) {
        array_push($get_joins, $join);
      }
    }
    return [
      'table'       => $params->table,
      'fields'      => $get_fields,
      'filters'     => $get_filters,
      'order'       => $get_order_by,
      'joins'       => $get_joins,
      'page'        => $get_page,
      'group_by'    => $get_group_by,
      'max_results' => $get_max_results,
    ];
  }
  /**
   * Obtiene el parametro search y los devuelve, se utliza en el metodo getParamsFilters
   * para utlizarlos como filtros de busqueda
   */
  private function getSearch($search_fields) {
    $contactenated_string = '';
    foreach ($search_fields as $key => $value) {
      $contactenated_string = $contactenated_string . ', ' . $value;
    }

    $contactenated_string = trim($contactenated_string, ', ');

    $string  = trim(strip_tags((string) $_GET['search']));
    $ignore  = ['la', 'lo', 'el', 'y', 'de', 'a', 'en', 'que', 'un', 'una', 'se', 'las', 'los', 'por', 'o', 'como', 'para', 'cual', 'del', 'al', 'con', 'es', 'esa', 'este', 'son', 'su', 'esta', 'ese'];
    $accents = [
      'a' => 'á',
      'e' => 'é',
      'i' => 'í',
      'o' => 'ó',
      'u' => 'ú',
      'n' => 'ñ',
      'A' => 'Á',
      'E' => 'É',
      'I' => 'Í',
      'O' => 'Ó',
      'U' => 'Ú',
      'N' => 'Ñ',
    ];

    foreach ($accents as $key => $value) {
      $string = preg_replace('#' . $value . '+#i', $key, (string) $string);
    }
    $string = strtolower($string);
    $string = preg_replace("/[^a-z0-9\s]+/i", ' ', (string) $string);
    $string = preg_replace("/[\s]+/", ',', (string) $string);
    $string = trim($string, ',');
    $string = explode(',', $string);

    $filters = [];
    foreach ($string as $key => $value) {
      if (!in_array($value, $ignore)) {
        $filters['search' . $key] = [$contactenated_string, $value, 'LIKE', 'CONCAT'];
      }
    }
    return $filters;
  }
  /**
   * Obtiene el parametro embed y convierte los valores en array los que son
   * devueltos para utilizarlos de la forma deseada, su principal uso puede
   * ser obtener datos adicionales que se adjuntarán a la respuesta
   */
  protected function getEmbed() {
    $embed = preg_replace("/([^a-zA-Z0-9,_\.]+)/", '', (string) $_GET['embed']);
    $embed = explode(',', $embed);
    return $embed;
  }

  private function getParamsFields() {
    $fields_params = preg_replace("/([^a-zA-Z0-9,_\.]+)/", '', (string) $_GET['fields']);
    $fields_params = explode(',', $fields_params);
    $fields = [];

    if (count($fields_params) > 0 && isset($_GET['fields']) && !empty($_GET['fields'])) {
      foreach ($fields_params as $key => $value) {
        if (array_key_exists($value, $this->availableFields) && $this->availableFields[$value]['listed'] === true && ($this->availableFields[$value]['roles'] === false || (defined('USER_ROLE') && in_array(USER_ROLE, $this->availableFields[$value]['roles'])))) {
          $fields[] = $this->availableFields[$value]['field'] . ' ' . $value;
        }
      }
    } else {
      foreach ($this->availableFields as $key => $value) {
        if (!empty($value['field']) && $value['listed'] === true && ($value['roles'] === false || (defined('USER_ROLE') && in_array(USER_ROLE, $value['roles'])))) {
          $fields[] = $value['field'] . ' ' . $key;
        }
      }
    }
    return $fields;
  }

  private function getParamsOrder() {
    $sort_params = strip_tags((string ) $_GET['sort']);
    $sort_params = explode(',', $sort_params);

    $order_by = [];
    if (isset($_GET['sort']) && !empty($_GET['sort']) && count($sort_params) > 0) {
      if (in_array('rand', $sort_params)) {
        $order_by[] = ['RAND()', ''];
      } else {
        foreach ($sort_params as $key => $value) {
          $f = substr($value, 1);
          if (array_key_exists($f, $this->availableFields)) {
            $o = substr($value, 0, 1);
            switch ($o) {
            case '+':
              $sorting = 'ASC';
              break;
            case '-':
              $sorting = 'DESC';
              break;
            default:
              $sorting = 'ASC';
              break;
            }
            $order_by[] = [$this->availableFields[$f]['field'], $sorting];
          }
        }
      }
    }
    return $order_by;
  }

  private function getParamsFilters($search_fields) {
    $filters = [];

    $operators = [
      'eq'  => '=',
      'lt'  => '<',
      'gt'  => '>',
      'gte' => '>=',
      'lte' => '<=',
      'ne'  => '!=',
      'lk'  => 'LIKE',
      'isn' => 'IS_NULL',
      'non' => 'NOT_NULL',
      'in'  => 'IN',
    ];

    foreach ($this->availableFields as $key => $value) {
      if (isset($_GET[$key]) && $value['filter'] == true) {
        $v = trim(strip_tags((string) $_GET[$key]));
        $v = explode(':', $v);
        if (array_key_exists($v[0], $operators)) {
          $filters[$key] = [$value['field'], $v[1], $operators[$v[0]]];
        } elseif(!isset($v[1]) && isset($v[0])) {
          $filters[$key] = [$value['field'], $v[0], $operators['eq']];
        }
      }
    }

    if (isset($_GET['search']) && !empty($_GET['search']) && $search_fields) {
      $search  = $this->getSearch($search_fields);
      $filters = array_merge($filters, $search);
    }
    return $filters;
  }

  private function getParamsGroup() {
    if (isset($_GET['groupby'])) {
      $group  = trim($_GET['groupby']);
      $params = explode(',', $group);
      $result = [];
      foreach ($params as $key => $param) {
        if (array_key_exists($param, $this->availableFields)) {
          $result[] = $this->availableFields[$param]['field'];
        }
      }
      if (count($result) > 0) {
        return $result;
      } else {
        return [];
      }
    } else {
      return [];
    }
  }

  public function get() {
    $get_params = $this->init();
    $result_type = isset($_GET['id']) ? NULL : 'list';
    $data = [
      'data' => []
    ];
    try {
      $data['data'] = DBGet::Get($get_params, $result_type);
    } catch(\AppException $e) {
      ApiResponse::Set($e->errorCode());
    }
    if (!$data['data']) {
      if(!$this->methodOptions['end']) {
        throw new \AppException('No results found', 404000);
      } else {
        ApiResponse::Set(404000);
      }
    } else {
      $this->embeded = $this->getEmbed();
      if (in_array('pagination', $this->embeded)) {
        $pagination_params = [
          'table'   => $get_params['table'],
          'filters' => $get_params['filters'],
          'joins'   => $get_params['joins'],
        ];
        if(count($get_params['group_by']) > 0) {
          $pagination_params['group_by'] = $get_params['group_by'];
          $result = DBGet::Get($pagination_params, 'list');
          $count = NULL;
          if($result) {
            $count = [
              'results' => count($result)
            ];
          }
        } else {
          $count = DBGet::Get($pagination_params, 'count');
        }
        if($count) {
          $pages = ceil($count['results'] / $get_params['max_results']);
          $data['pagination'] = [
            'pages'        => (int) $pages,
            'results'      => (int) $count['results'],
            'current_page' => (int) $get_params['page'],
          ];
        }
      }
    }
    if($this->methodOptions['end'] != True) {
      return $data;
    } else {
      ApiResponse::Set('SUCCESS', $data);
    }
  }

  public function post() {
    $get_params = $this->init();
    $table = preg_replace("/\s\w+?$/i", '', (string) $get_params['table']);
    $fields = $this->queryFields();
    $fv = new FieldsValidator();
    $validation = $fv->Validation($fields, $this->rules);
    if ($validation) {
      ApiResponse::Set(400000, [
        'errors'  => $validation
      ]);
    }
    if($this->queryFields) {
      foreach($this->queryFields as $key => $field) {
        $fields[$key] = $field;
      }
    }
    $f[] = $fields;
    try {
      $response = DBStore::Store($table, $f);
    } catch (\AppException $e) {
      ApiResponse::Set($e->errorCode());
    }
    if($this->methodOptions['end']) {
      ApiResponse::Set('CREATED', [
        'data' => [
          'inserted_id' => $response
        ]
      ]);
    }
    return $response;
  }

  public function put() {
    $get_params = $this->init();
    $fields = $this->queryFields();
    $fv = new FieldsValidator();
    $validation = $fv->Validation($fields, $this->rules, $this->entryId);
    if ($validation) {
      ApiResponse::Set(400000, [
        'errors'  => $validation
      ]);
    }
    if($this->queryFields) {
      foreach($this->queryFields as $key => $field) {
        $fields[$key] = $field;
      }
    }
    $f = $fields;
    try {
      $rows = DBUpdate::Update($get_params['table'], $f, $get_params['filters'], $get_params['joins']);
    } catch (\AppException $e) {
      ApiResponse::Set($e->errorCode());
    }
    if($this->methodOptions['end']) {
      $responseType = $rows > 0 ? 'UPDATED' : 'NOCHANGE';
      ApiResponse::Set($responseType, [
        'data' => [
          'rows' => $rows
        ]
      ]);
    }
    return $rows;
  }

  public function delete() {
	if(!ISSET($_GET['id']) || empty($_GET['id'])) {
	  ApiResponse::Set(400002, [
		'errors'  => ['id' => 'ID is required for delete']
	  ]);
	}
    $get_params = $this->init();
    try {
      $result = DBDelete::delete($get_params['table'], $get_params['filters'], $this->table_assoc);
    } catch (\AppException $e) {
      ApiResponse::Set($e->errorCode());
    }
    if($this->methodOptions['end']) {
      $responseType = $result == 0 ? 'NOCHANGE' : 'DELETED';
      ApiResponse::Set($responseType);
    }
    return $result;
  }

  protected function queryFields() {
    $vars   = [];
    foreach ($this->availableFields as $key => $field) {
	  $field_exists = array_key_exists($key, (array) $this->payload);
		// Si el campo no existe y es opcional no se agrega
      if((!$field_exists && $field['optional']) || (REQUEST_TYPE == 'update' && (empty($this->payload->$key) || !$field_exists)) || ($field['roles'] && !in_array(USER_ROLE, $field['roles'])) || !$field['saved'] ) {
        // No action
      } else {
        $value = (empty($this->payload->$key) && $field['default']) ? $field['default'] : $this->payload->$key;
        $vars[$key] = [$field['field'], $value];
      }
    }
    return $vars;
  }
  
  protected function controllerFields() {
    /**
     * [$this->available_fields description]
     * @var array
     * field[0] = Database column (ID should be always false when using getbyid)
     * field[1] = Can be used for filtering // TODO: OUTDATED REMOVE
     * field[2] = Can be saved
     * field[3] = Listed in search
     * field[4] = Default value in column if
     * field[5] = Add OPTIONAL if field is sent but does not need to be updated when empty useful for passwords
     * field['roles']: Solo se devuelve el resultado de esta columna si el rol de usuario se indica en el array ej. ('roles'=> [1,2,3,4,5])
     */
    $available_defaults = [
      'field'    => null,
      'filter'   => true,
      'saved'    => true,
      'listed'   => true,
      'default'  => null,
      'optional' => false,
      'roles'    => false,
    ];
    $response = [];
    foreach ($this->moduleFields as $name => $field) {
      foreach ($available_defaults as $key => $value) {
        if (!isset($this->moduleFields[$name][$key])) {
          $response[$name][$key] = $value;
        } else {
          $response[$name][$key] = $this->moduleFields[$name][$key];
        }
      }
    }
    $this->availableFields = $response;
  }
}
