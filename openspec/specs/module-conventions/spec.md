# Module Conventions

## Purpose

Describes how application modules are structured and configured in the Monitor Karewa backend, including the module files, the fields system, and the common module state. Agents MUST follow these conventions when creating or editing modules.

## Requirements

### Requirement: Module structure
Each module SHALL normally contain two files: `index.php`, which declares accepted methods and authorization rules and then calls `ModuleHandler::Validate()`, and `controller.php`, which defines the module class and extends `BaseModel`.

#### Scenario: Creating a module
- **WHEN** an agent adds a module
- **THEN** it provides an `index.php` that declares accepted methods and calls `ModuleHandler::Validate()`
- **AND** a `controller.php` whose class extends `BaseModel`

### Requirement: Module index pattern
The module `index.php` SHALL instantiate the component, declare `$accepted_methods`, and call `ModuleHandler::Validate($accepted_methods, $module)`. Each accepted method SHALL be declared as `[authenticated, roles]` (with optional alias and hash slots).

Reference pattern:

```php
require_once __DIR__ . '/controller.php';
$module = new ExampleComponent();

$accepted_methods = [
  'index'   => [false],
  'show'    => [false],
  'store'   => [true, [1, 2, 3]],
  'update'  => [true, [1, 2, 3]],
  'destroy' => [true, [1, 2, 3]],
];

ModuleHandler::Validate($accepted_methods, $module);
```

#### Scenario: Declaring accepted methods
- **WHEN** a module declares its accepted methods
- **THEN** each entry states whether authentication is required and which roles are allowed

### Requirement: BaseModel provides CRUD and shared behavior
`BaseModel` SHALL provide the `Crud` trait and common query and validation behavior used by every controller.

#### Scenario: Reusing shared behavior
- **WHEN** a controller needs standard create/read/update/delete behavior
- **THEN** it uses the `Crud` trait and `BaseModel` helpers instead of reimplementing them

### Requirement: Module fields configuration
`$moduleFields` SHALL support the following keys:

| Key | Default | Meaning |
| --- | --- | --- |
| `field` | `null` | Database column or expression |
| `filter` | `true` | Usable as a query filter |
| `saved` | `true` | Written on create/update |
| `listed` | `true` | Returned in GET responses |
| `default` | `null` | Fallback value |
| `optional` | `false` | Skip on update when missing |
| `roles` | `false` | Role IDs allowed to access the field |

#### Scenario: Declaring a field
- **WHEN** an agent adds a field to `$moduleFields`
- **THEN** it sets only the keys it needs and relies on the documented defaults for the rest

### Requirement: Common module state
A module SHALL configure its behavior through:
- `$get_params` containing `table`, `filters`, `joins`, `search`, and an optional `group`
- `$rules` for validation
- `$table_assoc` for cascading delete relationships
- `$methodOptions['end'] = false` to return raw data instead of terminating with `ApiResponse::Set()`

#### Scenario: Returning raw data
- **WHEN** a module needs to post-process results before responding
- **THEN** it sets `$methodOptions['end'] = false` and handles the response itself
