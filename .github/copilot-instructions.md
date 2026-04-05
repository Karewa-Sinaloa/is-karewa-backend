# Copilot Instructions — Monitor Karewa Backend

## Tech Stack

- **Language**: PHP 8.4+
- **Database**: MySQL 8.0+
- **Web server**: Apache (with `.htaccess` rewriting) or Nginx
- **Dependencies**: Managed via Composer in `app/core/third_party/`
- **Key packages**: `firebase/php-jwt`, `phpmailer/phpmailer`, `symfony/yaml`, `ramsey/uuid`, `smarty/smarty`, `phpunit/phpunit` (dev)

## Build & Test Commands

Run from `app/core/third_party/`:

```bash
# Install dependencies
composer install

# Run all tests
./vendor/bin/phpunit ../../test

# Run a single test file
./vendor/bin/phpunit ../../test/ValidationTest.php
```

## Architecture

This is a **custom PHP micro-framework** (not Laravel/Symfony). The entry point for all API traffic is `httpdocs/api.php`, which bootstraps `app/core/bootstrap/init.php`.

### Request Flow

```
HTTP request
  → Apache rewrites (.htaccess) → httpdocs/api.php
  → init.php (loads core, config, helpers, model layer)
  → midelware.php (determines REQUEST_TYPE from HTTP method + ?id param)
  → modules.php (resolves ?m=module-name to a module directory)
  → {module}/index.php (defines accepted_methods, instantiates controller, calls ModuleHandler::Validate)
  → {module}/controller.php (the model class, extends BaseModel)
```

### URL Routing

Apache rewrites map friendly URLs to query params:

| URL | Query |
|-----|-------|
| `/api/v5/materias` | `?m=materias` |
| `/api/v5/materias/42` | `?m=materias&id=42` |
| `/api/v5/materias/42/attachments` | `?m=materias&id=42&s=attachments` |

### Module Locations

- **App-specific modules**: `app/api/{module-name}/`
- **Core/shared modules**: `app/core/modules/{module-name}/`

Core modules take precedence when both exist. Modules use kebab-case directory names (e.g., `unidades-administrativas`).

### Module Structure

Each module contains exactly two files:

**`index.php`** — defines allowed HTTP methods and auth requirements, then delegates:
```php
require_once __DIR__ . '/controller.php';
$obj = new MyComponent();
$accepted_methods = [
  'index'   => [false],           // [auth_required]
  'show'    => [false],
  'store'   => [true, [1, 2, 3]], // [auth_required, allowed_roles]
  'update'  => [true, [1, 2, 3]],
  'destroy' => [true, [1, 2, 3]],
];
ModuleHandler::Validate($accepted_methods, $obj);
```

**`controller.php`** — the model class:
```php
class MyComponent extends BaseModel {
  use Crud;

  protected $moduleFields = [
    'id'   => ['field' => 'id', 'saved' => false],
    'name' => ['field' => 'name'],
  ];

  protected $get_params = [
    'table'   => 'my_table',
    'filters' => [],
    'joins'   => [],
    'search'  => [],
  ];

  protected $rules = [
    'name' => 'required|max:100|unique:my_table:name',
  ];
}
```

### `$moduleFields` Schema

Each field entry supports these keys (all have defaults — only `field` is required):

| Key | Default | Description |
|-----|---------|-------------|
| `field` | `null` | Database column name (or expression) |
| `filter` | `true` | Can be used as a URL query filter |
| `saved` | `true` | Written to DB on POST/PUT |
| `listed` | `true` | Included in GET responses |
| `default` | `null` | Default value if field is empty |
| `optional` | `false` | Skip on update if not provided |
| `roles` | `false` | Array of role IDs that can access this field; `false` = all |

### HTTP Method → Controller Method Mapping

| HTTP | `?id` present | `REQUEST_TYPE` | Method called |
|------|--------------|----------------|---------------|
| GET  | No  | `index`   | `index()` |
| GET  | Yes | `show`    | `show()` |
| POST | No  | `store`   | `store()` |
| PUT  | Yes | `update`  | `update()` |
| DELETE | Yes | `destroy` | `destroy()` |

### Authentication & Authorization

- JWT (RS256) with public/private keys in `app/.keys/`
- Pass the token as `Authorization` header
- `ModuleHandler::Validate()` checks the token and verifies `USER_ROLE` is in the allowed roles array
- `AUTHENTICATED` constant is defined as `true`/`false` after validation
- Roles are integers; role arrays in `accepted_methods` and `moduleFields` use the same values

### API Responses

All responses go through `ApiResponse::Set()`:

```php
ApiResponse::Set('SUCCESS', ['data' => $result]);  // named code
ApiResponse::Set(400000, ['errors' => $validation]); // numeric error code
```

Response codes and HTTP status codes are defined in `app/core/config/api_codes.yml`. Every response includes a `meta.session_id` field. `ApiResponse::Set()` always calls `die()`, so it terminates execution.

### Validation Rules

Rules are a pipe-separated string on `$rules`:

```
'required|max:150|email|unique:table:column|exist:table:column|numeric|alpha|alpha_dash|alpha_spaces|base64|date_format|time_format|decimal|rfc|url|boolean|json'
```

`FieldsValidator::Validation($fields, $rules, $id)` returns `array|false`. The `$id` parameter is used by `unique` to ignore the current record on updates.

### Global Variables

Set in `app/core/config/base.php` and available throughout:

- `$_config` — parsed `app/config.yml` as a `stdClass`
- `$_payload` — decoded JSON request body (POST/PUT), sanitized into a `stdClass`
- `$_apiConfig` — API configuration

### Configuration

- Config file: `app/config.yml` (not committed — encrypted as `app/config.yml.secret` via `git-secret`)
- Use `app/config.yml` as a reference for structure; copy from the example if available
- Composer dependencies are in `app/core/third_party/` (run `composer install` there, not at repo root)

### Namespaces

| Namespace | Location |
|-----------|----------|
| `App\Model` | `app/core/model/`, `app/core/bootstrap/methods.php` |
| `App\Auth` | `app/core/auth/` |
| `App\Helpers` | `app/core/helpers/` |
| `App\Validation` | `app/core/validation/` |

## Key Conventions

- **Slug generation**: Use `toAlphanumeric($string, '-')` (from `utilities.php`) — strips accents and special characters from Spanish text
- **Search normalization**: The search system strips Spanish accent marks and common stopwords before building LIKE filters — search fields defined in `$get_params['search']`
- **Pagination**: Request via `?embed=pagination`; also supports `?page=N`, `?limit=N`, `?sort=+field,-field`, `?groupby=field`, `?fields=field1,field2`
- **Filter operators**: URL filters use `?fieldname=op:value` syntax where op is `eq`, `lt`, `gt`, `gte`, `lte`, `ne`, `lk`, `isn`, `non`, `in`
- **Error logging**: Use `error_logs([$context, $code, $message, __LINE__, __FILE__])` — writes to `logs/` files configured in `app/config.yml`
- **`methodOptions['end']`**: Set `$this->methodOptions = ['end' => false]` in a controller to suppress the automatic `ApiResponse::Set()` call and return raw data instead (useful for composing responses manually)
- **Table associations for cascading deletes**: Define `$table_assoc` in the controller to handle FK constraint errors on `destroy()`
