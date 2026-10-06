# Tasks

## 1. Align config field map and search

- [x] 1.1 In `app/core/modules/config/controller.php`, replace the `data` field with `'value' => ['field' => 'value']` and change `search` to `['name', 'slug']` (remove `public`). Verify `php -l` passes and `rg "'data'|'public'" app/core/modules/config/controller.php` returns no matches in `$moduleFields`/`search`.
- [x] 1.2 Remove the `public` validation rule from `$rules`, leaving `name` and `slug`. Verify no rule references `public`.

## 2. Tests and documentation

- [x] 2.1 Add `tests/ConfigFieldMappingTest.php` that requires `CORE_PATH . 'bootstrap/midelware.php'` and the config controller, then asserts (via reflection on `$moduleFields`/`$get_params['search']`) that a `value` field exists, and that neither `data` nor `public` appears in fields, search, or rules. Verify `./vendor/bin/phpunit tests/ConfigFieldMappingTest.php` passes.
- [x] 2.2 Update `orm_wiki.md` so any `config` example uses `value` and does not mention `data`/`public`. Verify the documented field names match the corrected code.

## 3. Integration verification

- [x] 3.1 Run the full suite with `./vendor/bin/phpunit` (or `make test`) and confirm all tests pass.
- [x] 3.2 With `make up` and prefix `dev_`, call `GET /api/v5/config/3` and `GET /api/v5/config?search=mailing` and confirm both return the standard envelope with the `value` field and no `Unknown column 'data'`/`'public'` entry in `logs/error.log`.
