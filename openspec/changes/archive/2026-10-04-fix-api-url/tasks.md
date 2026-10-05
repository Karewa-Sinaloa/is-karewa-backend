# Tasks

## 1. Fix the API_URL constant

- [x] 1.1 In `app/core/config/base.php`, replace
  `define('API_URL', $_config->api)` with a definition that yields an absolute URL
  string: when `$_config->api` is a map, combine its `url` with the scheme implied
  by `https` (preserving a trailing slash and not double-prefixing an existing
  scheme); otherwise fall back to a string value. Verify `php -l` passes.
- [x] 1.2 Verify no other `base.php` URL constant has the same object-vs-string
  defect (`SITE_URL`, `CMS_URL`, `STATIC_URL`, `MAILINGS_URL`) and that `API_URL`
  is the only corrected line.

## 2. Tests and documentation

- [x] 2.1 Add `tests/ApiUrlTest.php` that loads `base.php` (or extracts the URL
  logic) with a map-shaped `api` node and asserts `API_URL` is a string beginning
  with `http` and containing the configured host/path. Verify
  `./vendor/bin/phpunit tests/ApiUrlTest.php` passes.
- [x] 2.2 Note the `API_URL` source-of-truth (`api.url` + `api.https`) in the
  project docs if they describe configuration constants; otherwise record it in
  the change only. (No doc describes API_URL; recorded in this change.)

## 3. Integration verification

- [x] 3.1 Run the full suite with `./vendor/bin/phpunit` (or `make test`) and
  confirm all tests pass.
- [x] 3.2 With `make up`, exercise `POST /api/v5/access/recovery` with an active
  user email and confirm no "Object of class stdClass could not be converted to
  string" error appears in `logs/error.log`/`logs/debug.log`. Verified: the
  request clears the `API_URL` build and reaches `loginMailer`; no `stdClass`
  conversion error is logged. Two unrelated pre-existing failures remain and are
  out of scope (owned by `fix-mailer-delivery`): the recovery-template fetch hits
  `ModuleHandler::Authenticate()` with an array `$hash` (TypeError), and the
  mailer sender address is null.
