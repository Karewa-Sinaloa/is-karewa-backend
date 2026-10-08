<?php

use PHPUnit\Framework\TestCase;

/**
 * Guards the published OpenAPI contract (api/docs/openapi.json) against drift:
 * security annotations vs each module's $accepted_methods, operation inventory
 * vs routed modules, error codes vs api_codes.yml, list 404 semantics,
 * operationId/envelope quality, and byte-identical published copies.
 */
final class OpenApiSyncTest extends TestCase
{
    private static array $spec;
    private static array $specB;

    public static function setUpBeforeClass(): void
    {
        self::$spec = json_decode(file_get_contents(self::specPath()), true);
        self::$specB = json_decode(file_get_contents(self::publishedCopyPath()), true);
    }

    private static function specPath(): string
    {
        return dirname(__DIR__) . '/api/docs/openapi.json';
    }

    private static function publishedCopyPath(): string
    {
        return dirname(__DIR__) . '/httpdocs/api/docs/openapi.json';
    }

    /** Parse `$accepted_methods` from a module index.php: [method => authRequired]. */
    private static function acceptedMethods(string $module): ?array
    {
        foreach ([dirname(__DIR__) . '/app/api/' . $module, dirname(__DIR__) . '/app/core/modules/' . $module] as $dir) {
            $file = $dir . '/index.php';
            if (!is_file($file)) continue;
            $text = file_get_contents($file);
            if (!preg_match('/\$accepted_methods\s*=\s*\[(.*?)\n\];/s', $text, $m)) return null;
            $methods = [];
            if (preg_match_all("/'(\w+)'\s*=>\s*\[\s*(true|false)/i", $m[1], $entries, PREG_SET_ORDER)) {
                foreach ($entries as $e) {
                    $methods[$e[1]] = strtolower($e[2]) === 'true';
                }
            }
            return $methods;
        }
        return null;
    }

    /** Operation security presence keyed by [METHOD path] => bool. */
    private function hasSecurity(string $method, string $path): bool
    {
        $op = self::$spec['paths'][$path][$method] ?? null;
        $this->assertIsArray($op, "Missing operation {$method} {$path}");
        return !empty($op['security']);
    }

    public function testSecurityMatchesAcceptedMethods(): void
    {
        $sample = [
            // [method, path, accepted_method_key]
            ['get', '/users', 'index'],
            ['get', '/users/{id}', 'show'],
            ['post', '/users', 'store'],
            ['put', '/users/{id}', 'update'],
            ['delete', '/users/{id}', 'destroy'],
            ['get', '/roles', 'index'],
            ['post', '/roles', 'store'],
            ['get', '/config', 'index'],
            ['get', '/config/{id}', 'show'],
            ['post', '/config', 'store'],
            ['get', '/contracts', 'index'],
            ['post', '/contracts', 'store'],
        ];
        foreach ($sample as [$method, $path, $key]) {
            $module = explode('/', trim($path, '/'))[0];
            $accepted = self::acceptedMethods($module);
            $this->assertIsArray($accepted, "No accepted_methods for {$module}");
            $this->assertArrayHasKey($key, $accepted, "Module {$module} lacks method {$key}");
            $expected = $accepted[$key];
            $actual = $this->hasSecurity($method, $path);
            $this->assertSame(
                $expected,
                $actual,
                "Security mismatch for {$method} {$path}: accepted[{$key}]=" . var_export($expected, true)
                    . ' but documented=' . var_export($actual, true)
            );
        }
    }

    public function testPublishedCopiesAreIdentical(): void
    {
        $a = file_get_contents(self::specPath());
        $b = file_get_contents(self::publishedCopyPath());
        $this->assertSame($a, $b, 'Published OpenAPI copies have drifted apart');
    }

    /** Minimal parser for api_codes.yml: [key => ['code' =>, 'http_code' =>, 'message' =>]]. */
    private static function apiCodes(): array
    {
        $codes = [];
        $current = null;
        foreach (file(dirname(__DIR__) . '/app/core/config/api_codes.yml') as $line) {
            if (preg_match('/^([A-Z0-9_]+|[0-9]{6}):\s*$/', $line, $m)) {
                $current = $m[1];
                $codes[$current] = [];
                continue;
            }
            if ($current === null) continue;
            if (preg_match('/^\s+message:\s*(.+)$/', $line, $m)) $codes[$current]['message'] = trim($m[1]);
            elseif (preg_match('/^\s+code:\s*(.+)$/', $line, $m)) $codes[$current]['code'] = trim($m[1]);
            elseif (preg_match('/^\s+http_code:\s*([0-9]+)\s*$/', $line, $m)) $codes[$current]['http_code'] = (int) $m[1];
        }
        return $codes;
    }

    /** Resolve a response object that may be a $ref into components/responses. */
    private function resolveResponse(array $response): array
    {
        if (!isset($response['$ref'])) return $response;
        $name = basename($response['$ref']);
        $resolved = self::$spec['components']['responses'][$name] ?? null;
        $this->assertIsArray($resolved, "Unresolved response reference {$response['$ref']}");
        return $resolved;
    }

    public function testDocumentedCodesExistInCatalog(): void
    {
        $catalog = self::apiCodes();
        $bySymbolic = [];
        foreach ($catalog as $key => $entry) {
            if (isset($entry['code'])) $bySymbolic[$entry['code']] = $entry;
        }
        $this->assertNotEmpty($bySymbolic);

        $checked = 0;
        foreach (self::$spec['paths'] as $path => $ops) {
            foreach ($ops as $verb => $op) {
                if (!is_array($op) || !isset($op['responses'])) continue;
                foreach ($op['responses'] as $status => $response) {
                    $resolved = $this->resolveResponse($response);
                    $example = $resolved['content']['application/json']['example'] ?? null;
                    if ($example === null || !isset($example['code'])) continue;
                    $symbolic = $example['code'];
                    $this->assertArrayHasKey(
                        $symbolic,
                        $bySymbolic,
                        "Documented code {$symbolic} ({$verb} {$path} status {$status}) is not in api_codes.yml"
                    );
                    $this->assertSame(
                        (string) $bySymbolic[$symbolic]['http_code'],
                        (string) $status,
                        "Code {$symbolic} documents http_code {$status} but catalog says {$bySymbolic[$symbolic]['http_code']}"
                    );
                    $this->assertSame(
                        $bySymbolic[$symbolic]['message'],
                        $example['message'],
                        "Code {$symbolic} example message does not match catalog message"
                    );
                    $checked++;
                }
            }
        }
        $this->assertGreaterThan(100, $checked, 'Expected to validate many documented examples');
    }

    public function testErrorResponsesAreSharedReferences(): void
    {
        $this->assertNotEmpty(self::$spec['components']['responses'] ?? [], 'components.responses must exist');
        $checked = 0;
        foreach (self::$spec['paths'] as $path => $ops) {
            foreach ($ops as $verb => $op) {
                if (!is_array($op) || !isset($op['responses'])) continue;
                foreach ($op['responses'] as $status => $response) {
                    if ((int) $status < 400) continue;
                    $this->assertArrayHasKey(
                        '$ref',
                        $response,
                        "Error response {$verb} {$path} status {$status} must be a \$ref to components/responses"
                    );
                    $this->assertMatchesRegularExpression(
                        '{^#/components/responses/Error[0-9A-Z_]+$}',
                        $response['$ref'],
                        "Unexpected error ref shape: {$response['$ref']}"
                    );
                    $checked++;
                }
            }
        }
        $this->assertGreaterThan(100, $checked);
    }

    public function testOperationIdsAreUniqueAndDeterministic(): void
    {
        $ids = [];
        foreach (self::$spec['paths'] as $path => $ops) {
            foreach ($ops as $verb => $op) {
                if (!is_array($op)) continue;
                $this->assertArrayHasKey('operationId', $op, "Missing operationId for {$verb} {$path}");
                $id = $op['operationId'];
                $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $id, "operationId {$id} is not normalized");
                if (isset($ids[$id])) {
                    $this->fail("Duplicate operationId {$id} ({$ids[$id]} vs {$verb} {$path})");
                }
                $ids[$id] = "{$verb} {$path}";
            }
        }
        $this->assertGreaterThan(50, $ids);

        // Determinism: regenerating from unchanged sources yields the same ids.
        $second = json_decode(file_get_contents(self::specPath()), true);
        $secondIds = [];
        foreach ($second['paths'] as $path => $ops) {
            foreach ($ops as $verb => $op) {
                if (is_array($op)) $secondIds[$op['operationId']] = true;
            }
        }
        $this->assertSame(array_keys($ids), array_keys($secondIds), 'operationIds changed between reads');
    }

    public function testSuccessResponsesDeclareSchema(): void
    {
        $jsonOps = 0;
        foreach (self::$spec['paths'] as $path => $ops) {
            foreach ($ops as $verb => $op) {
                if (!is_array($op) || !isset($op['responses'])) continue;
                foreach (['200', '201', '202'] as $status) {
                    if (!isset($op['responses'][$status])) continue;
                    $response = $op['responses'][$status];
                    $this->assertArrayNotHasKey('$ref', $response, "Success {$verb} {$path} {$status} must not be an error \$ref");
                    $content = $response['content'] ?? [];
                    $this->assertNotEmpty($content, "Success {$verb} {$path} {$status} has no content");
                    $schemaFound = false;
                    foreach ($content as $mediaType => $media) {
                        $this->assertArrayHasKey('schema', $media, "Success {$verb} {$path} {$status} ({$mediaType}) lacks schema");
                        $schemaFound = true;
                        if ($mediaType !== 'application/json') continue;
                        $schema = $media['schema'];
                        if (isset($schema['$ref'])) {
                            $this->assertSame('#/components/schemas/ResponseEnvelope', $schema['$ref']);
                            $jsonOps++;
                        } elseif (isset($schema['allOf'])) {
                            $refs = array_values(array_filter(array_column($schema['allOf'], '$ref')));
                            $this->assertContains('#/components/schemas/ResponseEnvelope', $refs, "Success {$verb} {$path} {$status} allOf must compose the envelope");
                            $sibling = array_values(array_filter($schema['allOf'], fn($s) => isset($s['properties']['allowed_roles'])));
                            $this->assertNotEmpty($sibling, "Success {$verb} {$path} {$status} must declare allowed_roles");
                            $jsonOps++;
                        }
                    }
                    $this->assertTrue($schemaFound);
                }
            }
        }
        $this->assertGreaterThan(50, $jsonOps, 'Expected most success responses to use the envelope schema');
        $this->assertArrayHasKey('ResponseEnvelope', self::$spec['components']['schemas']);
    }

    public function testAllowedRolesDocumentedOnlyForAuthenticatedOperations(): void
    {
        $secured = 0;
        $anonymous = 0;
        foreach (self::$spec['paths'] as $path => $ops) {
            foreach ($ops as $verb => $op) {
                if (!is_array($op) || !isset($op['responses'])) continue;
                foreach (['200', '201', '202'] as $status) {
                    $schema = $op['responses'][$status]['content']['application/json']['schema'] ?? null;
                    if ($schema === null) continue;
                    $encoded = json_encode($schema);
                    if (isset($op['security'])) {
                        $this->assertStringContainsString('allowed_roles', $encoded, "Secured {$verb} {$path} {$status} must document allowed_roles");
                        $secured++;
                    } else {
                        $this->assertStringNotContainsString('allowed_roles', $encoded, "Anonymous {$verb} {$path} {$status} must not document allowed_roles");
                        $anonymous++;
                    }
                }
            }
        }
        $this->assertGreaterThan(0, $secured, 'Expected at least one secured operation documenting allowed_roles');
        $this->assertGreaterThan(0, $anonymous, 'Expected at least one anonymous operation omitting allowed_roles');
    }

    public function testCollectionListingsHaveNo404AndEmptyExample(): void
    {
        foreach (self::$spec['paths'] as $path => $ops) {
            if (str_contains($path, '{id}')) continue;
            $op = $ops['get'] ?? null;
            if ($op === null || !isset($op['responses'])) continue;
            $this->assertArrayNotHasKey('404', $op['responses'], "Collection {$path} must not document 404");
            $example = $op['responses']['200']['content']['application/json']['example'] ?? null;
            if ($example !== null && array_key_exists('data', $example)) {
                $this->assertSame([], $example['data'], "Collection {$path} 200 example data must be an empty array");
            }
        }
    }

    /** Module directory (app/api first, then core/modules), mirroring moduleManager. */
    private static function moduleDir(string $module): ?string
    {
        foreach ([dirname(__DIR__) . '/app/api/' . $module, dirname(__DIR__) . '/app/core/modules/' . $module] as $dir) {
            if (is_dir($dir)) return $dir;
        }
        return null;
    }

    /** Methods the module class actually implements (explicit functions or the Crud trait). */
    private static function implementedMethods(string $module): array
    {
        $dir = self::moduleDir($module);
        if ($dir === null) return [];
        $all = '';
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $all .= file_get_contents($file) . "\n";
        }
        $methods = [];
        if (preg_match_all('/public function (index|show|store|update|destroy)\s*\(/', $all, $m)) {
            $methods = $m[1];
        }
        if (preg_match('/use Crud\b/', $all)) {
            $methods = array_unique(array_merge($methods, ['index', 'show', 'store', 'update', 'destroy']));
        }
        return $methods;
    }

    /** HTTP method + path suffix for each accepted method key. */
    private static function opFor(string $key): array
    {
        return match ($key) {
            'index' => ['get', ''],
            'show' => ['get', '/{id}'],
            'store' => ['post', ''],
            'update' => ['put', '/{id}'],
            'destroy' => ['delete', '/{id}'],
            default => [null, null],
        };
    }

    public function testOperationInventoryMatchesModules(): void
    {
        $special = ['access', 'docs']; // custom dispatchers, no $accepted_methods contract
        $modules = [];
        foreach ([dirname(__DIR__) . '/app/api', dirname(__DIR__) . '/app/core/modules'] as $base) {
            foreach (glob($base . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                $modules[basename($dir)] = true;
            }
        }
        $modules = array_keys($modules);

        foreach ($modules as $module) {
            if (in_array($module, $special, true)) continue;
            $accepted = self::acceptedMethods($module);
            if ($accepted === null) continue;
            $implemented = self::implementedMethods($module);
            $expected = array_intersect_key($accepted, array_flip($implemented));
            ksort($expected);

            $documented = [];
            foreach ($expected as $key => $_) {
                [$verb, $suffix] = self::opFor($key);
                if ($verb === null) continue;
                $path = '/' . $module . $suffix;
                if (isset(self::$spec['paths'][$path][$verb])) {
                    $documented[$key] = true;
                } else {
                    $this->fail("Module {$module}: accepted+implemented method '{$key}' is not documented as {$verb} {$path}");
                }
            }
            ksort($documented);
            $this->assertSame(
                array_keys($expected),
                array_keys($documented),
                "Documented inventory for {$module} does not match accepted∩implemented"
            );

            // Reverse: no documented operation outside accepted∩implemented.
            foreach (['' => 'index', '/{id}' => 'show'] as $suffix => $key) {
                foreach (['get', 'post', 'put', 'delete'] as $verb) {
                    if (!isset(self::$spec['paths']['/' . $module . $suffix][$verb])) continue;
                    $map = ['get' => $suffix === '' ? 'index' : 'show', 'post' => 'store', 'put' => 'update', 'delete' => 'destroy'];
                    $m = $map[$verb];
                    $this->assertArrayHasKey(
                        $m,
                        $expected,
                        "Module {$module}: documented {$verb} /{$module}{$suffix} is not accepted+implemented"
                    );
                }
            }
        }
    }

    /**
     * The config module maps id, name, slug and value; the last entry of its
     * `$moduleFields` array has no trailing comma, which used to drop `value`
     * from the contract on GET, POST and PUT.
     */
    public function testConfigDocumentsValueField(): void
    {
        $filterNames = array_column(self::$spec['paths']['/config']['get']['parameters'] ?? [], 'name');
        $this->assertContains('value', $filterNames, 'GET /config must document the value filter');

        foreach ([['post', '/config'], ['put', '/config/{id}']] as [$verb, $path]) {
            $schema = self::$spec['paths'][$path][$verb]['requestBody']['content']['application/json']['schema'];
            $this->assertArrayHasKey('value', $schema['properties'], strtoupper($verb) . ' ' . $path . ' must document value');
        }
    }

    /**
     * Row visibility is part of the contract: both config reads carry the
     * visibility rule as a description, the item operation requires no
     * security (show is anonymous), and is_private is documented as a boolean
     * field with default true while `data` and `public` stay undocumented.
     */
    public function testConfigDocumentsVisibilityFlag(): void
    {
        $listOp = self::$spec['paths']['/config']['get'] ?? null;
        $itemOp = self::$spec['paths']['/config/{id}']['get'] ?? null;
        $this->assertIsArray($listOp, 'GET /config must be documented');
        $this->assertIsArray($itemOp, 'GET /config/{id} must be documented');

        $this->assertArrayNotHasKey('security', $itemOp, 'GET /config/{id} is a public method and must declare no security');
        $this->assertArrayHasKey('description', $listOp, 'GET /config must describe the row visibility rule');
        $this->assertArrayHasKey('description', $itemOp, 'GET /config/{id} must describe the row visibility rule');
        $this->assertStringContainsString('is_private = 1', $listOp['description']);
        $this->assertStringContainsString('role 1, 2 or 3', $listOp['description']);
        $this->assertStringContainsString('404000', $itemOp['description']);

        $filterNames = array_column($listOp['parameters'] ?? [], 'name');
        $this->assertContains('is_private', $filterNames, 'GET /config must document the is_private filter');
        foreach (['data', 'public'] as $removed) {
            $this->assertNotContains($removed, $filterNames, "{$removed} must not be documented");
        }
        $flagParam = array_values(array_filter($listOp['parameters'], fn($p) => $p['name'] === 'is_private'))[0] ?? null;
        $this->assertSame('boolean', $flagParam['schema']['type'] ?? null, 'The is_private filter is a boolean');

        foreach ([['post', '/config'], ['put', '/config/{id}']] as [$verb, $path]) {
            $schema = self::$spec['paths'][$path][$verb]['requestBody']['content']['application/json']['schema'];
            foreach (['data', 'public'] as $removed) {
                $this->assertArrayNotHasKey($removed, $schema['properties'], strtoupper($verb) . ' ' . $path . " must not document {$removed}");
            }
            $flag = $schema['properties']['is_private'] ?? null;
            $this->assertIsArray($flag, strtoupper($verb) . ' ' . $path . ' must document is_private');
            $this->assertSame('boolean', $flag['type'], strtoupper($verb) . ' ' . $path);
            $this->assertTrue($flag['default'], strtoupper($verb) . ' ' . $path . ' must default to private');
        }

        $itemExample = $itemOp['responses']['200']['content']['application/json']['example']['data'] ?? null;
        $this->assertIsArray($itemExample);
        $this->assertTrue($itemExample['is_private'] ?? null, 'The item example must show a private entry');
    }

    /**
     * The config module documents per-row edit permissions: the write
     * operations answer 403 (APP_AUTH_ROW_FORBIDDEN) for a row the caller may
     * not modify, the list documents edit_roles as a filter, responses carry
     * the stored list, and no write body accepts it.
     */
    public function testConfigDocumentsRowEditPermissions(): void
    {
        foreach ([['put', '/config/{id}'], ['delete', '/config/{id}']] as [$verb, $path]) {
            $op = self::$spec['paths'][$path][$verb] ?? null;
            $this->assertIsArray($op, strtoupper($verb) . ' ' . $path . ' must be documented');
            $ref = $op['responses']['403']['$ref'] ?? null;
            $this->assertIsString($ref, strtoupper($verb) . ' ' . $path . ' must document 403');
            $example = $this->resolveResponse(['$ref' => $ref])['content']['application/json']['example'];
            $this->assertSame('APP_AUTH_ROW_FORBIDDEN', $example['code'], strtoupper($verb) . ' ' . $path);
            $this->assertSame(403, $example['http_code'], strtoupper($verb) . ' ' . $path);
        }

        $filterNames = array_column(self::$spec['paths']['/config']['get']['parameters'] ?? [], 'name');
        $this->assertContains('edit_roles', $filterNames, 'GET /config must document the edit_roles filter');

        $itemExample = self::$spec['paths']['/config/{id}']['get']['responses']['200']['content']['application/json']['example']['data'] ?? null;
        $this->assertIsArray($itemExample);
        $this->assertSame('1,2,3', $itemExample['edit_roles'] ?? null, 'Responses must expose the stored list');

        foreach ([['post', '/config'], ['put', '/config/{id}']] as [$verb, $path]) {
            $schema = self::$spec['paths'][$path][$verb]['requestBody']['content']['application/json']['schema'];
            $this->assertArrayNotHasKey(
                'edit_roles',
                $schema['properties'],
                strtoupper($verb) . ' ' . $path . ' must not accept a new list (configured in the database)'
            );
        }
    }

    /**
     * Counter-projection: regenerate the contract from the current modules into
     * a temp file and compare structurally with the published copy. Fails when
     * a controller/$moduleFields changed without regenerating.
     */
    public function testPublishedSpecMatchesRegeneratedOutput(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'openapi_') . '.json';
        $cmd = sprintf(
            'OPENAPI_OUT=%s php %s 2>&1',
            escapeshellarg($tmp),
            escapeshellarg(dirname(__DIR__) . '/tools/generate-openapi.php')
        );
        exec($cmd, $output, $exitCode);
        try {
            $this->assertSame(0, $exitCode, "Generator failed:\n" . implode("\n", $output));
            $this->assertFileExists($tmp);

            $regenerated = json_decode(file_get_contents($tmp), true);
            $published = json_decode(file_get_contents(self::specPath()), true);
            $this->assertIsArray($regenerated);
            $this->assertIsArray($published);
            $this->assertSame(
                $published,
                $regenerated,
                'Published openapi.json does not match generator output — run `php tools/generate-openapi.php`'
            );
        } finally {
            @unlink($tmp);
        }
    }
}
