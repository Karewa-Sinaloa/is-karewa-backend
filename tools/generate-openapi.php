<?php

$root = dirname(__DIR__);
$controllers = [
    'access' => $root . '/app/core/modules/access/local_login.php',
    'docs' => $root . '/app/api/docs/index.php',
    'pages' => $root . '/app/api/pages/controller.php',
    'users' => $root . '/app/core/modules/users/controller.php',
    'roles' => $root . '/app/core/modules/roles/controller.php',
    'organization' => $root . '/app/api/organization/controller.php',
    'contracts' => $root . '/app/api/contracts/controller.php',
    'estatus-contrato' => $root . '/app/api/estatus-contrato/controller.php',
    'materias' => $root . '/app/api/materias/controller.php',
    'partidas' => $root . '/app/api/partidas/controller.php',
    'periodos-contratos' => $root . '/app/api/periodos-contratos/controller.php',
    'procedimientos' => $root . '/app/api/procedimientos/controller.php',
    'proveedores' => $root . '/app/api/proveedores/controller.php',
    'tipo-contrato' => $root . '/app/api/tipo-contrato/controller.php',
    'unidades-administrativas' => $root . '/app/api/unidades-administrativas/controller.php',
    'unit-types' => $root . '/app/api/unit-types/controller.php',
    'config' => $root . '/app/core/modules/config/controller.php',
    'attachments' => $root . '/app/core/modules/attachments/controller.php',
    'image-upload' => $root . '/app/core/modules/image-upload/controller.php',
    'frontend-logs' => $root . '/app/core/modules/frontend-logs/controller.php',
    'mailings' => $root . '/app/core/modules/mailings/login_recovery.php',
    'hcaptcha' => $root . '/app/core/modules/hcaptcha/controller.php',
];

function extract_property_array(string $text, string $property): ?string {
    $needle = $property . ' =';
    $pos = strpos($text, $needle);
    if ($pos === false) return null;
    $start = strpos($text, '[', $pos);
    if ($start === false) return null;
    $depth = 0;
    $inSingle = false;
    $inDouble = false;
    $escape = false;
    $len = strlen($text);
    for ($i = $start; $i < $len; $i++) {
        $ch = $text[$i];
        if ($escape) {
            $escape = false;
            continue;
        }
        if ($ch === '\\') {
            $escape = true;
            continue;
        }
        if (!$inDouble && $ch === "'") {
            $inSingle = !$inSingle;
            continue;
        }
        if (!$inSingle && $ch === '"') {
            $inDouble = !$inDouble;
            continue;
        }
        if ($inSingle || $inDouble) continue;
        if ($ch === '[') $depth++;
        if ($ch === ']') {
            $depth--;
            if ($depth === 0) {
                return substr($text, $start, $i - $start + 1);
            }
        }
    }
    return null;
}

function parse_module_fields(string $block): array {
    $fields = [];
    if (preg_match_all("/'([^']+)'\s*=>\s*\[(.*?)\],/s", $block, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $name = $m[1];
            $inner = $m[2];
            $meta = [];
            if (preg_match_all("/'([^']+)'\s*=>\s*([^,\]]+|\[[^\]]*\])(?:,|$)/s", $inner, $pairs, PREG_SET_ORDER)) {
                foreach ($pairs as $p) {
                    $key = $p[1];
                    $raw = trim($p[2]);
                    if ($raw === 'true') $value = true;
                    elseif ($raw === 'false') $value = false;
                    elseif ($raw === 'NULL' || $raw === 'null') $value = null;
                    elseif ($raw !== '' && $raw[0] === '[') {
                        $value = [];
                        if (preg_match_all("/'([^']+)'/", $raw, $arrMatches)) {
                            $value = $arrMatches[1];
                        }
                    } elseif ($raw !== '' && ($raw[0] === '"' || $raw[0] === "'")) {
                        $value = trim($raw, "'\"");
                    } elseif (is_numeric($raw)) {
                        $value = $raw + 0;
                    } else {
                        $value = $raw;
                    }
                    $meta[$key] = $value;
                }
            }
            $fields[$name] = $meta;
        }
    }
    return $fields;
}

function parse_search_fields(string $block): array {
    if (preg_match("/'search'\s*=>\s*\[(.*?)\]/s", $block, $m)) {
        if (preg_match_all("/'([^']+)'/", $m[1], $fields)) {
            return $fields[1];
        }
    }
    return [];
}

function parse_rules(string $text): array {
    $block = extract_property_array($text, '$rules');
    if ($block === null) return [];
    $rules = [];
    if (preg_match_all("/'([^']+)'\s*=>\s*'([^']*)'/", $block, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $rules[$m[1]] = $m[2];
        }
    }
    return $rules;
}

function rule_parts(string $rule): array {
    if ($rule === '') return [];
    return array_values(array_filter(explode('|', $rule), fn($part) => $part !== ''));
}

function rule_names(array $parts): array {
    return array_map(fn($part) => explode(':', $part)[0], $parts);
}

/** Fields the API writes on store/update: present in the field map, saved and not read-only. */
function is_sendable(array $field): bool {
    $m = field_meta($field);
    return $m['saved'] === true && $m['readonly'] !== true;
}

function extract_accepted_methods(string $indexPath): ?array {
    $text = @file_get_contents($indexPath);
    if ($text === false) return null;
    $block = extract_property_array($text, '$accepted_methods');
    if ($block === null) return null;
    $methods = [];
    if (preg_match_all("/'(\w+)'\s*=>\s*\[\s*(true|false)\b/i", $block, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $methods[$m[1]] = strtolower($m[2]) === 'true';
        }
    }
    return $methods ?: null;
}

function apply_security(array &$op, array $accepted, string $method): void {
    if (($accepted[$method] ?? false) === true) {
        $op['security'] = [['bearerAuth' => []]];
    }
}

function field_meta(array $field): array {
    return [
        'field' => $field['field'] ?? null,
        'listed' => $field['listed'] ?? true,
        'filter' => $field['filter'] ?? true,
        'saved' => $field['saved'] ?? true,
        'default' => $field['default'] ?? null,
        'optional' => $field['optional'] ?? false,
        'roles' => $field['roles'] ?? false,
        'readonly' => $field['readonly'] ?? false,
        'internal' => (($field['listed'] ?? true) === false) || (($field['saved'] ?? true) === false) || (($field['filter'] ?? true) === false),
    ];
}

function load_api_codes(string $path): array {
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) return [];
    $codes = [];
    $current = null;
    foreach ($lines as $line) {
        if (preg_match('/^([A-Z0-9_]+|[0-9]{6}):\s*$/', $line, $m)) {
            $current = $m[1];
            $codes[$current] = [];
            continue;
        }
        if ($current === null) continue;
        if (preg_match('/^\s+message:\s*(.+)$/', $line, $m)) {
            $codes[$current]['message'] = trim($m[1]);
        } elseif (preg_match('/^\s+code:\s*(.+)$/', $line, $m)) {
            $codes[$current]['code'] = trim($m[1]);
        } elseif (preg_match('/^\s+http_code:\s*([0-9]+)\s*$/', $line, $m)) {
            $codes[$current]['http_code'] = (int) $m[1];
        }
    }
    return $codes;
}

function guess_field_kind(string $name): string {
    if ($name === 'email') return 'email';
    if (in_array($name, ['password', 'token', 'code'], true)) return 'secret';
    if (preg_match('/(^|_)(id|status_id|role_id|period_id|provider_id|admin_unit_type_id|partida_id|contract_type_id|organization_id|subject_id|procedure_id|applicant_admin_unit_id|organizer_admin_unit_id)$/', $name)) return 'integer';
    if (preg_match('/(date|updated_at|created_at|dob|birthday|period)/', $name)) return 'date';
    if (in_array($name, ['amount_was_exceeded', 'phone_verified', 'email_verified', 'public'], true)) return 'boolean';
    if (in_array($name, ['total_amount', 'min_amount', 'max_amount', 'subtotal', 'exceeded_amount'], true)) return 'number';
    if (in_array($name, ['postal_code', 'phone', 'phone_country_code'], true)) return 'integer';
    return 'string';
}

function build_filter_examples(string $name): array {
    $kind = guess_field_kind($name);
    $examples = [];
    if (in_array($kind, ['integer', 'number'], true)) {
        $examples = [
            'equals' => ['summary' => 'Equal', 'value' => 'eq:1'],
            'lessThan' => ['summary' => 'Less than', 'value' => 'lt:10'],
            'greaterThan' => ['summary' => 'Greater than', 'value' => 'gt:10'],
            'greaterOrEqual' => ['summary' => 'Greater or equal', 'value' => 'gte:10'],
            'lessOrEqual' => ['summary' => 'Less or equal', 'value' => 'lte:10'],
            'notEqual' => ['summary' => 'Not equal', 'value' => 'ne:10'],
            'inList' => ['summary' => 'In list', 'value' => 'in:1,2,3'],
        ];
    } elseif ($kind === 'date') {
        $examples = [
            'equals' => ['summary' => 'Equal', 'value' => 'eq:2026-08-21'],
            'lessThan' => ['summary' => 'Before', 'value' => 'lt:2026-08-21'],
            'greaterThan' => ['summary' => 'After', 'value' => 'gt:2026-08-21'],
            'greaterOrEqual' => ['summary' => 'On or after', 'value' => 'gte:2026-08-21'],
            'lessOrEqual' => ['summary' => 'On or before', 'value' => 'lte:2026-08-21'],
            'notEqual' => ['summary' => 'Not equal', 'value' => 'ne:2026-08-21'],
        ];
    } elseif ($kind === 'boolean') {
        $examples = [
            'equals' => ['summary' => 'Equal', 'value' => 'eq:1'],
            'notEqual' => ['summary' => 'Not equal', 'value' => 'ne:0'],
            'isNull' => ['summary' => 'Is null', 'value' => 'isn'],
            'notNull' => ['summary' => 'Is not null', 'value' => 'non'],
        ];
    } else {
        $examples = [
            'equals' => ['summary' => 'Equal', 'value' => 'eq:texto'],
            'like' => ['summary' => 'Contains', 'value' => 'lk:texto'],
            'notEqual' => ['summary' => 'Not equal', 'value' => 'ne:texto'],
            'isNull' => ['summary' => 'Is null', 'value' => 'isn'],
            'notNull' => ['summary' => 'Is not null', 'value' => 'non'],
            'inList' => ['summary' => 'In list', 'value' => 'in:texto1,texto2'],
        ];
    }
    return $examples;
}

/**
 * Readable code samples (cURL + axios) attached as `x-codeSamples` on every operation.
 *
 * The docs page sets `hiddenClients: true`, so Scalar's own clients are hidden: they
 * percent-encode the examples (`fields=id%2Cname`, `sort=%2Bid%2C-name`), which is not
 * readable for a human. The API accepts the query characters unencoded, so these
 * samples keep them verbatim, and the host is the public API origin.
 */
function build_code_samples(string $path, string $method, array $op): array {
    $method = strtoupper($method);
    $server = ($path === '/docs' || str_starts_with($path, '/api/docs')) ? '' : '/api/v5';
    $resolvedPath = str_replace('{id}', '1', $path);

    $params = [];
    foreach ($op['parameters'] ?? [] as $param) {
        if (($param['in'] ?? '') !== 'query' || !array_key_exists('example', $param)) continue;
        $value = is_array($param['example']) ? ($param['example']['value'] ?? '') : $param['example'];
        $params[$param['name']] = (string) $value;
    }
    $body = $op['requestBody']['content']['application/json']['example'] ?? null;
    $authenticated = isset($op['security']);

    $url = 'https://kapi.chavodigital.com' . $server . $resolvedPath;
    if ($params) {
        $pairs = [];
        foreach ($params as $name => $value) $pairs[] = $name . '=' . $value;
        $url .= '?' . implode('&', $pairs);
    }

    return [
        ['lang' => 'Shell', 'label' => 'cURL', 'source' => build_curl_source($method, $url, $body, $authenticated)],
        ['lang' => 'JavaScript', 'label' => 'axios', 'source' => build_axios_source($method, $url, $params, $body, $authenticated)],
    ];
}

function build_curl_source(string $method, string $url, ?array $body, bool $authenticated): string {
    $parts = ['curl'];
    if ($method !== 'GET') $parts[] = '--request ' . $method;
    $parts[] = "--url '" . $url . "'";
    if ($body !== null) {
        $parts[] = "--header 'Content-Type: application/json'";
        $parts[] = "--data '" . json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "'";
    }
    if ($authenticated) $parts[] = "--header 'Authorization: Bearer YOUR_SECRET_TOKEN'";
    return implode(" \\\n  ", $parts);
}

function build_axios_source(string $method, string $url, array $params, ?array $body, bool $authenticated): string {
    $headers = [];
    if ($body !== null) $headers['Content-Type'] = 'application/json';
    if ($authenticated) $headers['Authorization'] = 'Bearer YOUR_SECRET_TOKEN';

    $lines = ["import axios from 'axios';", '', 'const options = {'];
    $lines[] = "  method: '" . $method . "',";
    $lines[] = "  url: '" . $url . "',";
    if ($params) $lines[] = '  params: ' . js_literal($params, 1) . ',';
    if ($body !== null) $lines[] = '  data: ' . js_literal($body, 1) . ',';
    if ($headers) $lines[] = '  headers: ' . js_literal($headers, 1) . ',';
    $lines[] = '};';
    $lines[] = '';
    $lines[] = 'try {';
    $lines[] = '  const { data } = await axios.request(options);';
    $lines[] = '  console.log(data);';
    $lines[] = '} catch (error) {';
    $lines[] = '  console.error(error);';
    $lines[] = '}';
    return implode("\n", $lines);
}

/** Renders a PHP value as an indented JavaScript literal (2 spaces per level). */
function js_literal($value, int $depth): string {
    $pad = str_repeat('  ', $depth);
    $inner = str_repeat('  ', $depth + 1);
    if (is_array($value)) {
        $associative = array_keys($value) !== range(0, count($value) - 1);
        if (!$value) return $associative ? '{}' : '[]';
        $items = [];
        foreach ($value as $key => $item) {
            $rendered = js_literal($item, $depth + 1);
            $items[] = $associative
                ? (preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', (string) $key) ? $key : json_encode((string) $key)) . ': ' . $rendered
                : $rendered;
        }
        return ($associative ? '{' : '[') . "\n" . $inner . implode(",\n" . $inner, $items) . "\n" . $pad . ($associative ? '}' : ']');
    }
    if (is_bool($value)) return $value ? 'true' : 'false';
    if ($value === null) return 'null';
    if (is_int($value) || is_float($value)) return (string) $value;
    return json_encode((string) $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function build_query_param_definitions(array $fields, array $searchFields, array $rules = []): array {
    $params = [];
    $names = array_keys($fields);
    $listedNames = [];
    foreach ($fields as $name => $meta) {
        if (field_meta($meta)['listed']) $listedNames[] = $name;
    }
    $first = $names[0] ?? 'id';
    $second = $names[1] ?? null;
    $firstListed = $listedNames[0] ?? $first;
    $secondListed = $listedNames[1] ?? null;

    $params[] = ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1], 'description' => 'Page number', 'example' => 1];
    $params[] = ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1], 'description' => 'Max results per page', 'example' => 25];
    if ($searchFields) {
        $params[] = ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Full-text search over: ' . implode(', ', $searchFields) . '. Accents and stopwords are normalized before matching.', 'example' => 'texto'];
    }
    $params[] = [
        'name' => 'sort',
        'in' => 'query',
        'schema' => ['type' => 'string'],
        'description' => 'Comma-separated sort list, prefix each field with + (ascending) or - (descending). Allowed fields: ' . implode(', ', $names) . '.',
        'example' => $second === null ? '+' . $first : '+' . $first . ',-' . $second,
        'examples' => [
            'ascending' => ['summary' => 'Ascending', 'value' => '+' . $first],
            'descending' => ['summary' => 'Descending', 'value' => '-' . $first],
        ],
    ];
    $params[] = [
        'name' => 'groupby',
        'in' => 'query',
        'schema' => ['type' => 'string'],
        'description' => 'Comma-separated grouping fields. Allowed fields: ' . implode(', ', $names) . '.',
        'example' => $first,
    ];
    $params[] = [
        'name' => 'embed',
        'in' => 'query',
        'schema' => ['type' => 'string', 'enum' => ['pagination']],
        'description' => 'Comma-separated embeddings. Supported values: pagination (adds pages, results and current_page to meta).',
        'example' => 'pagination',
    ];
    $params[] = [
        'name' => 'fields',
        'in' => 'query',
        'schema' => ['type' => 'string'],
        'description' => 'Comma-separated output fields. Allowed fields: ' . implode(', ', $listedNames) . '.',
        'example' => $secondListed === null ? $firstListed : implode(',', [$firstListed, $secondListed]),
    ];
    foreach ($fields as $name => $meta) {
        $m = field_meta($meta);
        if (!$m['filter']) continue;
        $notes = [];
        if (!$m['saved'] || $m['readonly']) $notes[] = 'Read-only field (not writable on POST/PUT).';
        if (!$m['listed']) $notes[] = 'Internal field, not returned in responses.';
        if ($m['roles']) $notes[] = 'Only visible for roles ' . implode(', ', (array) $m['roles']) . '.';
        $ruleDetail = describe_rules(rule_parts($rules[$name] ?? ''), false);
        if ($ruleDetail) $notes[] = $ruleDetail;
        $params[] = [
            'name' => $name,
            'in' => 'query',
            'schema' => ['type' => 'string'],
            'description' => trim('Filter on ' . $name . ' using campo=op:valor with operators eq, lt, gt, gte, lte, ne, lk, isn, non, in. ' . implode(' ', $notes)),
            'example' => guess_field_kind($name) === 'string' ? 'lk:texto' : 'eq:1',
            'examples' => build_filter_examples($name),
        ];
    }
    return $params;
}

/** Human readable summary of a module validation rule list, e.g. "Required. Max 100 characters." */
function describe_rules(array $parts, bool $withRequired = true): string {
    if (!$parts) return '';
    $out = [];
    $names = rule_names($parts);
    foreach ($parts as $part) {
        $bits = explode(':', $part);
        $name = $bits[0];
        $arg = $bits[1] ?? '';
        switch ($name) {
            case 'required':
                if ($withRequired) $out[] = 'Required';
                break;
            case 'max':
                if (is_numeric($arg)) $out[] = 'Max ' . (int) $arg . ' characters';
                break;
            case 'min':
                if (is_numeric($arg)) $out[] = 'Min ' . (int) $arg . ' characters';
                break;
            case 'max_value':
                $out[] = 'Max value ' . $arg;
                break;
            case 'min_value':
                $out[] = 'Min value ' . $arg;
                break;
            case 'exist':
                $out[] = 'Must reference an existing row in ' . ($bits[1] ?? '?') . '.' . ($bits[2] ?? '');
                break;
            case 'unique':
                $out[] = 'Must be unique in ' . ($bits[1] ?? '?') . '.' . ($bits[2] ?? '');
                break;
            case 'email':
                $out[] = 'Valid email';
                break;
            case 'url':
                $out[] = 'Valid URL';
                break;
            case 'rfc':
                $out[] = 'Valid RFC';
                break;
            case 'date_format':
                $out[] = 'Date formatted as YYYY-MM-DD';
                break;
            case 'time_format':
                $out[] = 'Time formatted as HH:MM:SS';
                break;
            case 'boolean':
                $out[] = 'Boolean';
                break;
            case 'decimal':
                $out[] = 'Decimal number';
                break;
            case 'numeric':
                $out[] = 'Numeric';
                break;
            case 'json':
                $out[] = 'JSON string';
                break;
            case 'base64':
                $out[] = 'Base64 string';
                break;
            case 'alpha':
                $out[] = 'Letters only';
                break;
            case 'alpha_dash':
                $out[] = 'Letters, numbers, dashes and underscores';
                break;
            case 'alpha_spaces':
                $out[] = 'Letters and spaces';
                break;
        }
    }
    if ($withRequired && !in_array('required', $names, true)) {
        array_unshift($out, 'Optional');
    }
    return implode('. ', $out) . (count($out) ? '.' : '');
}

function error_response_example(array $codeData, bool $withErrors = false): array {
    $response = [
        'message' => $codeData['message'] ?? 'Error',
        'http_code' => $codeData['http_code'] ?? 500,
        'code' => $codeData['code'] ?? 'APP_INTERNAL_SERVER_ERROR',
        'meta' => ['session_id' => 'uuid'],
    ];
    if ($withErrors) {
        $response['errors'] = ['field' => 'validation message'];
    }
    return $response;
}

function add_error_responses(array &$op, array $codes, string $kind, array $omit = []): void {
    global $errorResponses;
    $selected = ['400000', '400001', '400002', '404000', '900000', '902000', '902001', '902002', '909000', '902003', '429000'];
    if ($kind === 'access') {
        $selected = array_merge($selected, ['901001', '901002', '901003', '901004', '901005', '901006', '901007', '903000', '905000']);
    }
    if ($kind === 'upload') {
        $selected = array_merge($selected, ['906000', '906001', '906002', '906003']);
    }
    if ($kind === 'mailing') {
        $selected = array_merge($selected, ['903000', '905000']);
    }
    if ($kind === 'docs') {
        $selected = ['429000'];
    }
    $selected = array_values(array_diff(array_unique($selected), $omit));
    foreach ($selected as $code) {
        if (!isset($codes[$code])) continue;
        $status = (string) ($codes[$code]['http_code'] ?? 500);
        $withErrors = in_array($code, ['400000', '400001', '400002'], true);
        $name = 'Error' . $code;
        $errorResponses[$name] = [
            'description' => $codes[$code]['message'] ?? 'Error',
            'content' => [
                'application/json' => [
                    'example' => error_response_example($codes[$code], $withErrors),
                ],
            ],
        ];
        $op['responses'][$status] = ['$ref' => '#/components/responses/' . $name];
    }
}

$paths = [];
$tags = ['Docs'];
$moduleFieldsMap = [];
$searchFieldsMap = [];
$moduleRulesMap = [];
$acceptedMethodsMap = [];
$errorResponses = [];
$apiCodes = load_api_codes($root . '/app/core/config/api_codes.yml');

foreach ($controllers as $module => $path) {
    $text = file_get_contents($path);
    if ($text === false) continue;
    $moduleFieldsRaw = extract_property_array($text, '$moduleFields');
    if ($moduleFieldsRaw) {
        $moduleFieldsMap[$module] = parse_module_fields($moduleFieldsRaw);
    }
    $moduleRulesMap[$module] = parse_rules($text);
    $getParamsRaw = extract_property_array($text, '$get_params');
    if ($getParamsRaw) {
        $searchFieldsMap[$module] = parse_search_fields($getParamsRaw);
    }
    $indexPath = dirname($path) . '/index.php';
    $accepted = is_file($indexPath) ? extract_accepted_methods($indexPath) : null;
    if ($accepted !== null) {
        $acceptedMethodsMap[$module] = $accepted;
    }
}

if (isset($argv[1]) && $argv[1] === '--accepted-methods') {
    echo json_encode($acceptedMethodsMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$apiRoutes = [
    'access' => ['login' => 'post', 'logout' => 'get', 'recovery' => 'post', 'reset' => 'post'],
    'pages' => ['index' => 'get', 'show' => 'get', 'store' => 'post', 'update' => 'put', 'destroy' => 'delete'],
    'users' => ['index' => 'get', 'show' => 'get', 'store' => 'post', 'update' => 'put', 'destroy' => 'delete'],
    'roles' => ['index' => 'get', 'show' => 'get', 'store' => 'post', 'update' => 'put', 'destroy' => 'delete'],
    'organization' => ['index' => 'get', 'show' => 'get', 'store' => 'post', 'update' => 'put'],
    'contracts' => ['index' => 'get', 'show' => 'get', 'store' => 'post', 'update' => 'put', 'destroy' => 'delete'],
    'estatus-contrato' => ['index' => 'get', 'show' => 'get', 'store' => 'post', 'update' => 'put', 'destroy' => 'delete'],
    'materias' => ['index' => 'get', 'show' => 'get', 'store' => 'post', 'update' => 'put', 'destroy' => 'delete'],
    'partidas' => ['index' => 'get', 'show' => 'get', 'store' => 'post', 'update' => 'put', 'destroy' => 'delete'],
    'periodos-contratos' => ['index' => 'get', 'show' => 'get', 'store' => 'post', 'update' => 'put', 'destroy' => 'delete'],
    'procedimientos' => ['index' => 'get', 'show' => 'get', 'store' => 'post', 'update' => 'put', 'destroy' => 'delete'],
    'proveedores' => ['index' => 'get', 'show' => 'get', 'store' => 'post', 'update' => 'put', 'destroy' => 'delete'],
    'tipo-contrato' => ['index' => 'get', 'show' => 'get', 'store' => 'post', 'update' => 'put', 'destroy' => 'delete'],
    'unidades-administrativas' => ['index' => 'get', 'show' => 'get', 'store' => 'post', 'update' => 'put', 'destroy' => 'delete'],
    'unit-types' => ['index' => 'get', 'show' => 'get', 'store' => 'post', 'update' => 'put', 'destroy' => 'delete'],
    'config' => ['index' => 'get', 'show' => 'get', 'store' => 'post', 'update' => 'put', 'destroy' => 'delete'],
    'attachments' => ['store' => 'post'],
    'image-upload' => ['index' => 'get', 'show' => 'get', 'store' => 'post', 'destroy' => 'delete'],
    'frontend-logs' => ['store' => 'post'],
    'mailings' => ['index' => 'get'],
    'docs' => ['index' => 'get'],
    'hcaptcha' => ['store' => 'post'],
];

function schema_type_for_field(string $name, array $parts): array {
    $names = rule_names($parts);
    $schema = ['type' => 'string'];
    if (preg_match('/(^|_)(id|status_id|role_id|period_id|provider_id|admin_unit_type_id|partida_id|contract_type_id|organization_id|subject_id|procedure_id|applicant_admin_unit_id|organizer_admin_unit_id)$/', $name)) {
        $schema = ['type' => 'integer'];
    }
    if (preg_match('/(date|updated_at|created_at|dob|birthday|period)/', $name)) {
        $schema = ['type' => 'string'];
    }
    if (in_array($name, ['amount_was_exceeded', 'phone_verified', 'email_verified'], true)) {
        $schema = ['type' => 'boolean'];
    }
    if (in_array($name, ['total_amount', 'min_amount', 'max_amount', 'subtotal', 'exceeded_amount'], true)) {
        $schema = ['type' => 'number'];
    }
    if (in_array('boolean', $names, true)) $schema = ['type' => 'boolean'];
    if (in_array('decimal', $names, true)) {
        $schema = ['type' => 'number'];
    } elseif (in_array('numeric', $names, true) && $schema['type'] !== 'number') {
        $schema = ['type' => 'integer'];
    }
    if (in_array('email', $names, true)) {
        $schema = ['type' => 'string', 'format' => 'email'];
    } elseif (in_array('url', $names, true)) {
        $schema = ['type' => 'string', 'format' => 'uri'];
    } elseif (in_array('date_format', $names, true)) {
        $schema = ['type' => 'string', 'format' => 'date'];
    } elseif (in_array('time_format', $names, true)) {
        $schema = ['type' => 'string', 'format' => 'time'];
    }
    foreach ($parts as $part) {
        $bits = explode(':', $part);
        $arg = $bits[1] ?? '';
        if (!is_numeric($arg)) continue;
        if ($bits[0] === 'max' && $schema['type'] === 'string') $schema['maxLength'] = (int) $arg;
        if ($bits[0] === 'min' && $schema['type'] === 'string') $schema['minLength'] = (int) $arg;
        if ($bits[0] === 'max_value') $schema['maximum'] = (int) $arg;
        if ($bits[0] === 'min_value') $schema['minimum'] = (int) $arg;
    }
    return $schema;
}

/**
 * Request body schema for store/update: only fields the API writes, with the
 * `required` list derived from the module `$rules` on create.
 */
function build_request_schema(array $fields, array $rules, string $method): array {
    $props = [];
    $required = [];
    foreach ($fields as $name => $meta) {
        if (!is_sendable($meta)) continue;
        $m = field_meta($meta);
        $parts = rule_parts($rules[$name] ?? '');
        $isCreate = $method === 'store';
        if ($isCreate && in_array('required', rule_names($parts), true)) {
            $required[] = $name;
        }
        $prop = schema_type_for_field($name, $parts);
        $notes = [];
        $detail = describe_rules($parts, $isCreate);
        if ($detail) $notes[] = $detail;
        if ($m['optional']) $notes[] = 'May be omitted when creating.';
        if (!$m['listed']) $notes[] = 'Not returned in responses.';
        if (!$m['filter']) $notes[] = 'Not usable as a query filter.';
        if ($m['roles']) $notes[] = 'Writable only for roles ' . implode(', ', (array) $m['roles']) . '.';
        $prop['description'] = $notes ? implode(' ', $notes) : 'Optional field.';
        if ($m['default'] !== null) {
            $prop['default'] = $m['default'];
        }
        $props[$name] = $prop;
    }
    $schema = [
        'type' => 'object',
        'properties' => $props ?: new stdClass(),
        'additionalProperties' => true,
    ];
    if (!$props) {
        $schema['description'] = 'No writable fields are declared for this endpoint.';
        return $schema;
    }
    if ($method === 'store') {
        if ($required) $schema['required'] = $required;
        $schema['description'] = 'Create payload with every writable field of the module. Fields listed under required must be sent; the others are optional and fall back to their default when omitted.';
    } else {
        $schema['description'] = 'Partial update: only fields sent with a non-empty value are updated, omitted or empty fields keep their current value.';
    }
    return $schema;
}

function example_value_for_field(string $name, array $meta = []): mixed {
    if ($name === 'email') return 'admin@example.com';
    if ($name === 'password') return 'Secret123!';
    if ($name === 'token') return 'hcaptcha-token';
    if ($name === 'code') return 123456;
    if (preg_match('/(^|_)(id|status_id|role_id|period_id|provider_id|admin_unit_type_id|partida_id|contract_type_id|organization_id|subject_id|procedure_id|applicant_admin_unit_id|organizer_admin_unit_id)$/', $name)) {
        return 1;
    }
    if (preg_match('/(date|updated_at|created_at|dob|birthday|period)/', $name)) {
        return '2026-08-21';
    }
    if (in_array($name, ['total_amount', 'min_amount', 'max_amount', 'subtotal', 'exceeded_amount'], true)) {
        return 1000.5;
    }
    if (in_array($name, ['amount_was_exceeded', 'phone_verified', 'email_verified'], true)) {
        return false;
    }
    if ($name === 'public') return 0;
    if ($name === 'slug') return 'example-slug';
    if ($name === 'name') return 'Example name';
    if ($name === 'shortname') return 'Example';
    if ($name === 'contact_email') return 'contact@example.com';
    if ($name === 'first_name') return 'Jane';
    if ($name === 'last_name') return 'Doe';
    if ($name === 'phone') return '5555555555';
    if ($name === 'phone_country_code') return 52;
    if ($name === 'photo') return 'https://example.com/photo.jpg';
    if ($name === 'call_link' || $name === 'proposal_url' || $name === 'proposals_url' || $name === 'contract_link') return 'https://example.com/resource';
    if ($name === 'contract_number') return 'CT-2026-0001';
    if ($name === 'area_in_charge') return 'Procurement';
    if ($name === 'notes' || $name === 'organization_notes' || $name === 'work_description') return 'Example description';
    if ($name === 'postal_code') return 12345;
    if ($name === 'state') return 'CDMX';
    if ($name === 'city') return 'Mexico City';
    if ($name === 'colonia') return 'Centro';
    if ($name === 'street') return 'Main street 123';
    if ($name === 'data') return '{}';
    return 'example';
}

function build_example_request(array $fields, string $module, string $method = ''): ?array {
    $payload = [];
    foreach ($fields as $name => $meta) {
        $m = field_meta($meta);
        if (!is_sendable($meta)) continue;
        if ($name === 'recovery_code') continue;
        $payload[$name] = example_value_for_field($name, $meta);
    }
    if (!$payload) return null;
    if ($module === 'access') {
        $requests = [
            'login' => ['email' => 'admin@example.com', 'password' => 'Secret123!', 'token' => 'hcaptcha-token'],
            'recovery' => ['email' => 'admin@example.com'],
            'reset' => ['email' => 'admin@example.com', 'code' => 123456, 'password' => 'Secret123!'],
        ];
        return $requests[$method] ?? null;
    }
    return $payload;
}

function build_example_response(array $fields, string $method, string $module): array {
    if ($module === 'access') {
        if ($method === 'login') {
            return [
                'message' => 'Success',
                'code' => 'SUCCESS',
                'http_code' => 200,
                'data' => [
                    'access_token' => 'jwt-token',
                    'expires_in' => 3600,
                    'role_id' => 1,
                    'name' => 'Jane',
                    'last_name' => 'Doe',
                    'email' => 'admin@example.com',
                    'user_id' => 1,
                ],
                'meta' => ['session_id' => 'uuid'],
            ];
        }
        if ($method === 'recovery') {
            return ['message' => 'Success', 'code' => 'SUCCESS', 'http_code' => 200, 'meta' => ['session_id' => 'uuid']];
        }
        if ($method === 'reset') {
            return ['message' => 'Success', 'code' => 'SUCCESS', 'http_code' => 200, 'meta' => ['session_id' => 'uuid']];
        }
        if ($method === 'logout') {
            return ['message' => 'Success', 'code' => 'SUCCESS', 'http_code' => 200, 'data' => ['data' => 'Logged out successfully'], 'meta' => ['session_id' => 'uuid']];
        }
    }

    $item = [];
    foreach ($fields as $name => $meta) {
        $m = field_meta($meta);
        if (!$m['listed']) continue;
        $item[$name] = example_value_for_field($name, $meta);
    }

    if ($method === 'index' || $method === 'show') {
        return [
            'message' => 'Success',
            'code' => 'SUCCESS',
            'http_code' => 200,
            'data' => $method === 'index' ? [$item] : $item,
            'meta' => ['session_id' => 'uuid'],
        ];
    }

    if ($method === 'store') {
        return [
            'message' => 'Entry added',
            'code' => 'APP_CREATE_SUCCESS',
            'http_code' => 201,
            'data' => ['inserted_id' => 1],
            'meta' => ['session_id' => 'uuid'],
        ];
    }

    if ($method === 'update') {
        return [
            'message' => 'Entry updated',
            'code' => 'APP_UPDATE_SUCCESS',
            'http_code' => 200,
            'data' => ['rows' => 1],
            'meta' => ['session_id' => 'uuid'],
        ];
    }

    if ($method === 'destroy') {
        return [
            'message' => 'Entry deleted correctly',
            'code' => 'DELETED',
            'http_code' => 202,
            'meta' => ['session_id' => 'uuid'],
        ];
    }

    return [];
}

function attach_examples(array &$op, array $fields, string $module, string $method): void {
    $request = build_example_request($fields, $module, $method);
    $response = build_example_response($fields, $method, $module);
    if ($request && in_array($method, ['store', 'update', 'login', 'recovery', 'reset'], true)) {
        $op['requestBody']['content']['application/json']['example'] = $request;
    }
    if ($response) {
        $status = match ($method) {
            'store' => '201',
            'destroy' => '202',
            default => '200',
        };
        $op['responses'][$status]['content']['application/json']['example'] = $response;
    }
}

foreach ($apiRoutes as $module => $methods) {
    $fields = $moduleFieldsMap[$module] ?? [];
    $searchFields = $searchFieldsMap[$module] ?? [];
    $base = '/' . $module;
    if ($module === 'docs') {
        $paths['/api/docs'] = ['get' => ['tags' => ['Docs'], 'summary' => 'Docs UI', 'responses' => ['200' => ['description' => 'HTML UI']]]];
        $paths['/docs'] = ['get' => ['tags' => ['Docs'], 'summary' => 'Docs landing page', 'responses' => ['200' => ['description' => 'HTML UI']]]];
        $paths['/api/docs/openapi.json'] = ['get' => ['tags' => ['Docs'], 'summary' => 'OpenAPI document', 'responses' => ['200' => ['description' => 'OpenAPI JSON']]]];
        foreach (['/api/docs', '/docs', '/api/docs/openapi.json'] as $docPath) {
            add_error_responses($paths[$docPath]['get'], $apiCodes, 'docs');
        }
        continue;
    }
    if ($module === 'hcaptcha') {
        $op = [
            'tags' => ['Hcaptcha'],
            'summary' => 'Generate hCaptcha bypass secret',
            'requestBody' => ['required' => false, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => new stdClass(), 'additionalProperties' => false]]]],
            'responses' => [
                '200' => [
                    'description' => 'Plaintext bypass secret, returned once',
                    'content' => ['application/json' => ['example' => [
                        'message' => 'Success',
                        'code' => 'SUCCESS',
                        'http_code' => 200,
                        'data' => ['hcaptcha_bypass' => 'x7Kp2sQ9vR4m'],
                        'meta' => ['session_id' => 'uuid'],
                    ]]],
                ],
            ],
        ];
        apply_security($op, $acceptedMethodsMap[$module] ?? [], 'store');
        add_error_responses($op, $apiCodes, 'crud');
        $paths[$base]['post'] = $op;
        continue;
    }
    if ($module === 'access') {
        $paths[$base . '/login'] = [
            'post' => [
                'tags' => ['Access'],
                'summary' => 'Login',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['email', 'password', 'token'],
                                'properties' => [
                                    'email' => ['type' => 'string', 'format' => 'email'],
                                    'password' => ['type' => 'string'],
                                    'token' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => ['200' => ['description' => 'Session token']]
            ]
        ];
        attach_examples($paths[$base . '/login']['post'], $fields, $module, 'login');
        add_error_responses($paths[$base . '/login']['post'], $apiCodes, 'access');
        $paths[$base . '/logout'] = ['get' => ['tags' => ['Access'], 'summary' => 'Logout', 'responses' => ['200' => ['description' => 'Logged out']]]];
        add_error_responses($paths[$base . '/logout']['get'], $apiCodes, 'access', ['404000', '400000', '400001', '400002']);
        $paths[$base . '/recovery'] = [
            'post' => [
                'tags' => ['Access'],
                'summary' => 'Send password recovery code',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['email'],
                                'properties' => [
                                    'email' => ['type' => 'string', 'format' => 'email'],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => ['200' => ['description' => 'Recovery sent']],
            ],
        ];
        attach_examples($paths[$base . '/recovery']['post'], $fields, $module, 'recovery');
        add_error_responses($paths[$base . '/recovery']['post'], $apiCodes, 'access', ['404000']);
        $paths[$base . '/reset'] = [
            'post' => [
                'tags' => ['Access'],
                'summary' => 'Reset password',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['email', 'code', 'password'],
                                'properties' => [
                                    'email' => ['type' => 'string', 'format' => 'email'],
                                    'code' => ['type' => 'integer'],
                                    'password' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => ['200' => ['description' => 'Password updated']],
            ],
        ];
        attach_examples($paths[$base . '/reset']['post'], $fields, $module, 'reset');
        add_error_responses($paths[$base . '/reset']['post'], $apiCodes, 'access', ['404000']);
        continue;
    }
    $collectionOp = [
        'tags' => [ucwords(str_replace('-', ' ', $module))],
        'summary' => 'List ' . str_replace('-', ' ', $module),
        'responses' => ['200' => ['description' => 'List response']],
    ];
    $collectionOp['parameters'] = build_query_param_definitions($fields, $searchFields, $moduleRulesMap[$module] ?? []);
    $collectionOp['responses']['200']['content'] = ['application/json' => ['example' => [
        'message' => 'Success',
        'code' => 'SUCCESS',
        'http_code' => 200,
        'data' => [],
        'meta' => ['session_id' => 'uuid'],
    ]]];
    apply_security($collectionOp, $acceptedMethodsMap[$module] ?? [], 'index');
    add_error_responses($collectionOp, $apiCodes, in_array($module, ['attachments', 'image-upload'], true) ? 'upload' : (in_array($module, ['access'], true) ? 'access' : (in_array($module, ['mailings'], true) ? 'mailing' : 'crud')), ['404000']);
    if (isset($methods['index'])) {
        $paths[$base] = [strtolower($methods['index']) => $collectionOp];
    }
    if (isset($methods['show'])) {
        $op = ['tags' => [ucwords(str_replace('-', ' ', $module))], 'summary' => 'Get ' . str_replace('-', ' ', $module), 'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'responses' => ['200' => ['description' => 'Item response']]];
        apply_security($op, $acceptedMethodsMap[$module] ?? [], 'show');
        attach_examples($op, $fields, $module, 'show');
        add_error_responses($op, $apiCodes, in_array($module, ['attachments', 'image-upload'], true) ? 'upload' : 'crud');
        $paths[$base . '/{id}']['get'] = $op;
    }
    if (isset($methods['store'])) {
        $op = ['tags' => [ucwords(str_replace('-', ' ', $module))], 'summary' => 'Create ' . str_replace('-', ' ', $module), 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => build_request_schema($fields, $moduleRulesMap[$module] ?? [], 'store')]]], 'responses' => ['201' => ['description' => 'Created']]];
        apply_security($op, $acceptedMethodsMap[$module] ?? [], 'store');
        attach_examples($op, $fields, $module, 'store');
        add_error_responses($op, $apiCodes, in_array($module, ['attachments', 'image-upload'], true) ? 'upload' : (in_array($module, ['access'], true) ? 'access' : (in_array($module, ['mailings'], true) ? 'mailing' : 'crud')));
        $paths[$base]['post'] = $op;
    }
    if (isset($methods['update'])) {
        $op = ['tags' => [ucwords(str_replace('-', ' ', $module))], 'summary' => 'Update ' . str_replace('-', ' ', $module), 'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => build_request_schema($fields, $moduleRulesMap[$module] ?? [], 'update')]]], 'responses' => ['200' => ['description' => 'Updated']]];
        apply_security($op, $acceptedMethodsMap[$module] ?? [], 'update');
        attach_examples($op, $fields, $module, 'update');
        add_error_responses($op, $apiCodes, 'crud');
        $paths[$base . '/{id}']['put'] = $op;
    }
    if (isset($methods['destroy'])) {
        $op = ['tags' => [ucwords(str_replace('-', ' ', $module))], 'summary' => 'Delete ' . str_replace('-', ' ', $module), 'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'responses' => ['200' => ['description' => 'Deleted']]];
        apply_security($op, $acceptedMethodsMap[$module] ?? [], 'destroy');
        attach_examples($op, $fields, $module, 'destroy');
        add_error_responses($op, $apiCodes, 'crud');
        $paths[$base . '/{id}']['delete'] = $op;
    }
}

foreach ($paths as $path => &$item) {
    foreach ($item as $method => &$op) {
        if (!isset($op['tags']) && $path === '/api/docs/openapi.json') $op['tags'] = ['Docs'];
        // Deterministic operationId from method + path only (task 4.2).
        $normalizedPath = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $path), '_'));
        $op['operationId'] = strtolower($method) . '_' . $normalizedPath;
        // Readable samples: the docs page hides Scalar's generated clients because
        // those percent-encode the examples, so every operation carries its own.
        $op['x-codeSamples'] = build_code_samples($path, $method, $op);
        // Every success response declares a schema instead of a bare description (task 4.3).
        foreach (['200', '201', '202'] as $status) {
            if (!isset($op['responses'][$status]) || isset($op['responses'][$status]['$ref'])) continue;
            $response = $op['responses'][$status];
            if ($path === '/docs' || $path === '/api/docs' || $path === '/mailings') {
                // HTML surfaces: document the real content type, not a JSON envelope.
                $op['responses'][$status]['content'] = ['text/html' => ['schema' => ['type' => 'string']]];
            } elseif ($path === '/api/docs/openapi.json') {
                $op['responses'][$status]['content'] = ['application/json' => ['schema' => ['type' => 'object', 'additionalProperties' => true]]];
            } else {
                if (!isset($response['content']['application/json'])) {
                    $response['content']['application/json'] = [];
                }
                $response['content']['application/json']['schema'] = ['$ref' => '#/components/schemas/ResponseEnvelope'];
                $op['responses'][$status] = $response;
            }
        }
    }
}
unset($item, $op);

// Prune shared error responses that no operation ended up referencing
// (several codes share one HTTP status, so only the last $ref survives).
$referencedResponses = [];
foreach ($paths as $item) {
    foreach ($item as $op) {
        foreach ($op['responses'] ?? [] as $response) {
            if (isset($response['$ref'])) {
                $referencedResponses[basename($response['$ref'])] = true;
            }
        }
    }
}
$errorResponses = array_intersect_key($errorResponses, $referencedResponses);
ksort($errorResponses);

$spec = [
    'openapi' => '3.0.3',
    'info' => ['title' => 'Monitor Karewa API', 'version' => '5.0.0', 'description' => 'Public OpenAPI contract for the Monitor Karewa REST API.'],
    'servers' => [['url' => '/api/v5'], ['url' => '/']],
    'tags' => array_map(fn($name) => ['name' => $name], ['Docs','Access','Pages','Users','Roles','Organization','Contracts','Estatus Contrato','Materias','Partidas','Periodos Contratos','Procedimientos','Proveedores','Tipo Contrato','Unidades Administrativas','Unit Types','Attachments','Config','Image Upload','Frontend Logs','Mailings','Hcaptcha']),
    'components' => [
        'securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT']],
        'responses' => $errorResponses,
        'schemas' => [
            'ResponseEnvelope' => [
                'type' => 'object',
                'required' => ['message', 'code', 'http_code', 'meta'],
                'properties' => [
                    'message' => ['type' => 'string'],
                    'code' => ['type' => 'string'],
                    'http_code' => ['type' => 'integer'],
                    'data' => ['description' => 'Payload; shape depends on the endpoint (object, array, or scalar)'],
                    'meta' => ['type' => 'object', 'properties' => ['session_id' => ['type' => 'string']]],
                ],
            ],
            'GenericObject' => ['type' => 'object', 'additionalProperties' => true],
            'GenericList' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/GenericObject']],
            'LoginRequest' => ['type' => 'object', 'required' => ['email', 'password', 'token'], 'properties' => ['email' => ['type' => 'string', 'format' => 'email'], 'password' => ['type' => 'string'], 'token' => ['type' => 'string']]],
            'RecoveryRequest' => ['type' => 'object', 'required' => ['email'], 'properties' => ['email' => ['type' => 'string', 'format' => 'email']]],
            'ResetRequest' => ['type' => 'object', 'required' => ['email', 'code', 'password'], 'properties' => ['email' => ['type' => 'string', 'format' => 'email'], 'code' => ['type' => 'integer'], 'password' => ['type' => 'string']]],
            'UploadResult' => ['type' => 'object', 'additionalProperties' => true],
        ],
    ],
    'paths' => $paths,
];

$json = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
// OPENAPI_OUT overrides the primary output path (used by the sync test to
// regenerate into a temp file without touching the published copies).
$out = getenv('OPENAPI_OUT');
if ($out !== false && $out !== '') {
    file_put_contents($out, $json);
    echo "OpenAPI generated to {$out}\n";
    exit(0);
}
file_put_contents($root . '/api/docs/openapi.json', $json);
@mkdir($root . '/httpdocs/api/docs', 0775, true);
file_put_contents($root . '/httpdocs/api/docs/openapi.json', $json);
echo "OpenAPI generated\n";
