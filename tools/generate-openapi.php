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

function endpoint_security(string $module, string $method, array $methodDef): array {
    $auth = $methodDef[0] ?? false;
    if ($module === 'docs') return [];
    if (!$auth) return [];
    return [['bearerAuth' => []]];
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

function build_query_param_definitions(array $fields, array $searchFields): array {
    $params = [];
    $params[] = ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1], 'description' => 'Page number', 'example' => 1];
    $params[] = ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1], 'description' => 'Max results per page', 'example' => 25];
    if ($searchFields) {
        $params[] = ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Full-text search over the module search fields', 'example' => 'texto de prueba'];
    }
    $params[] = ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Comma-separated sort list, e.g. +field,-field', 'example' => '+created_at,-id'];
    $params[] = ['name' => 'groupby', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Comma-separated grouping fields', 'example' => 'status_id,role_id'];
    $params[] = ['name' => 'embed', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Comma-separated embeddings, e.g. pagination', 'example' => 'pagination'];
    $params[] = ['name' => 'fields', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Comma-separated output fields', 'example' => 'id,name'];
    foreach ($fields as $name => $meta) {
        $m = field_meta($meta);
        if ($m['filter']) {
            $params[] = [
                'name' => $name,
                'in' => 'query',
                'schema' => ['type' => 'string'],
                'description' => $m['internal'] ? 'Internal filter field. Use campo=op:valor with operators eq, lt, gt, gte, lte, ne, lk, isn, non, in.' : 'Filterable field. Use campo=op:valor with operators eq, lt, gt, gte, lte, ne, lk, isn, non, in.',
                'example' => guess_field_kind($name) === 'string' ? 'lk:texto' : 'eq:1',
                'examples' => build_filter_examples($name),
            ];
        }
    }
    return $params;
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

function add_error_responses(array &$op, array $codes, string $kind): void {
    $selected = ['400000', '400001', '400002', '404000', '900000', '902000', '902001', '902002', '909000'];
    if ($kind === 'access') {
        $selected = array_merge($selected, ['901001', '901002', '901003', '901004', '901005', '901006', '901007', '903000', '905000']);
    }
    if ($kind === 'upload') {
        $selected = array_merge($selected, ['906000', '906001', '906002', '906003']);
    }
    if ($kind === 'mailing') {
        $selected = array_merge($selected, ['903000', '905000']);
    }
    $selected = array_values(array_unique($selected));
    foreach ($selected as $code) {
        if (!isset($codes[$code])) continue;
        $status = (string) ($codes[$code]['http_code'] ?? 500);
        $withErrors = in_array($code, ['400000', '400001', '400002'], true);
        $op['responses'][$status] = [
            'description' => $codes[$code]['message'] ?? 'Error',
            'content' => [
                'application/json' => [
                    'example' => error_response_example($codes[$code], $withErrors),
                ],
            ],
        ];
    }
}

$paths = [];
$tags = ['Docs'];
$moduleFieldsMap = [];
$searchFieldsMap = [];
$apiCodes = load_api_codes($root . '/app/core/config/api_codes.yml');

foreach ($controllers as $module => $path) {
    $text = file_get_contents($path);
    if ($text === false) continue;
    $moduleFieldsRaw = extract_property_array($text, '$moduleFields');
    if ($moduleFieldsRaw) {
        $moduleFieldsMap[$module] = parse_module_fields($moduleFieldsRaw);
    }
    $getParamsRaw = extract_property_array($text, '$get_params');
    if ($getParamsRaw) {
        $searchFieldsMap[$module] = parse_search_fields($getParamsRaw);
    }
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
];

function build_schema(array $fields, string $module): array {
    $props = [];
    foreach ($fields as $name => $meta) {
        $m = field_meta($meta);
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
        $props[$name] = $schema + [
            'description' => $m['internal'] ? 'Internal field' : 'Public field',
        ];
        if ($m['default'] !== null) {
            $props[$name]['default'] = $m['default'];
        }
    }
    return ['type' => 'object', 'properties' => $props, 'additionalProperties' => true];
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

function build_example_request(array $fields, string $module): ?array {
    $payload = [];
    foreach ($fields as $name => $meta) {
        $m = field_meta($meta);
        if (!$m['saved'] || $m['readonly']) continue;
        if ($name === 'recovery_code') continue;
        $payload[$name] = example_value_for_field($name, $meta);
    }
    if (!$payload) return null;
    if ($module === 'access') {
        return [
            'login' => ['email' => 'admin@example.com', 'password' => 'Secret123!', 'token' => 'hcaptcha-token'],
            'recovery' => ['email' => 'admin@example.com'],
            'reset' => ['email' => 'admin@example.com', 'code' => 123456, 'password' => 'Secret123!'],
        ];
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
    $request = build_example_request($fields, $module);
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
        $paths[$base . '/logout'] = ['get' => ['tags' => ['Access'], 'summary' => 'Logout', 'responses' => ['200' => ['description' => 'Logged out']]]];
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
        continue;
    }
    $collectionOp = [
        'tags' => [ucwords(str_replace('-', ' ', $module))],
        'summary' => 'List ' . str_replace('-', ' ', $module),
        'responses' => ['200' => ['description' => 'List response']],
    ];
    $collectionOp['parameters'] = build_query_param_definitions($fields, $searchFields);
    add_error_responses($collectionOp, $apiCodes, in_array($module, ['attachments', 'image-upload'], true) ? 'upload' : (in_array($module, ['access'], true) ? 'access' : (in_array($module, ['mailings'], true) ? 'mailing' : 'crud')));
    $paths[$base] = [strtolower($methods['index'] ?? 'get') => $collectionOp];
    if (isset($methods['show'])) {
        $op = ['tags' => [ucwords(str_replace('-', ' ', $module))], 'summary' => 'Get ' . str_replace('-', ' ', $module), 'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'responses' => ['200' => ['description' => 'Item response']]];
        attach_examples($op, $fields, $module, 'show');
        add_error_responses($op, $apiCodes, in_array($module, ['attachments', 'image-upload'], true) ? 'upload' : 'crud');
        $paths[$base . '/{id}']['get'] = $op;
    }
    if (isset($methods['store'])) {
        $op = ['tags' => [ucwords(str_replace('-', ' ', $module))], 'summary' => 'Create ' . str_replace('-', ' ', $module), 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => build_schema($fields, $module)]]], 'responses' => ['201' => ['description' => 'Created']]];
        $op['security'] = endpoint_security($module, 'store', [true]);
        attach_examples($op, $fields, $module, 'store');
        add_error_responses($op, $apiCodes, in_array($module, ['attachments', 'image-upload'], true) ? 'upload' : (in_array($module, ['access'], true) ? 'access' : (in_array($module, ['mailings'], true) ? 'mailing' : 'crud')));
        $paths[$base]['post'] = $op;
    }
    if (isset($methods['update'])) {
        $op = ['tags' => [ucwords(str_replace('-', ' ', $module))], 'summary' => 'Update ' . str_replace('-', ' ', $module), 'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => build_schema($fields, $module)]]], 'responses' => ['200' => ['description' => 'Updated']]];
        $op['security'] = endpoint_security($module, 'update', [true]);
        attach_examples($op, $fields, $module, 'update');
        add_error_responses($op, $apiCodes, 'crud');
        $paths[$base . '/{id}']['put'] = $op;
    }
    if (isset($methods['destroy'])) {
        $op = ['tags' => [ucwords(str_replace('-', ' ', $module))], 'summary' => 'Delete ' . str_replace('-', ' ', $module), 'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'responses' => ['200' => ['description' => 'Deleted']]];
        $op['security'] = endpoint_security($module, 'destroy', [true]);
        attach_examples($op, $fields, $module, 'destroy');
        add_error_responses($op, $apiCodes, 'crud');
        $paths[$base . '/{id}']['delete'] = $op;
    }
}

foreach ($paths as $path => &$item) {
    foreach ($item as $method => &$op) {
        if (!isset($op['tags']) && $path === '/api/docs/openapi.json') $op['tags'] = ['Docs'];
    }
}
unset($item, $op);

$spec = [
    'openapi' => '3.0.3',
    'info' => ['title' => 'Monitor Karewa API', 'version' => '5.0.0', 'description' => 'Public OpenAPI contract for the Monitor Karewa REST API.'],
    'servers' => [['url' => '/api/v5'], ['url' => '/']],
    'tags' => array_map(fn($name) => ['name' => $name], ['Docs','Access','Pages','Users','Roles','Organization','Contracts','Estatus Contrato','Materias','Partidas','Periodos Contratos','Procedimientos','Proveedores','Tipo Contrato','Unidades Administrativas','Unit Types','Attachments','Config','Image Upload','Frontend Logs','Mailings']),
    'components' => ['securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT']], 'schemas' => ['GenericObject' => ['type' => 'object', 'additionalProperties' => true], 'GenericList' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/GenericObject']], 'LoginRequest' => ['type' => 'object', 'required' => ['email', 'password', 'token'], 'properties' => ['email' => ['type' => 'string', 'format' => 'email'], 'password' => ['type' => 'string'], 'token' => ['type' => 'string']]], 'RecoveryRequest' => ['type' => 'object', 'required' => ['email'], 'properties' => ['email' => ['type' => 'string', 'format' => 'email']]], 'ResetRequest' => ['type' => 'object', 'required' => ['email', 'code', 'password'], 'properties' => ['email' => ['type' => 'string', 'format' => 'email'], 'code' => ['type' => 'integer'], 'password' => ['type' => 'string']]], 'UploadResult' => ['type' => 'object', 'additionalProperties' => true]]],
    'paths' => $paths,
];

$json = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
file_put_contents($root . '/api/docs/openapi.json', $json);
@mkdir($root . '/httpdocs/api/docs', 0775, true);
file_put_contents($root . '/httpdocs/api/docs/openapi.json', $json);
echo "OpenAPI generated\n";
