# Tasks

## 1. Fix the unknown-code fallback

- [ ] 1.1 In `app/core/helpers/api_response.php`, restructure the response resolution so the fallback object is used when `$codes` is null or the code is missing, instead of being overwritten (for example: `$response = ($codes && isset($codes->$code)) ? $codes->$code : (object) $fallback;`), and remove the now-redundant `if (!$codes ...)` / unconditional reassignment. Verify `php -l app/core/helpers/api_response.php` passes.
- [ ] 1.2 Fix the log message at the parse-failure branch so the log file path is separated from the message text (the current string concatenates `ERROR_LOG_FILE` with no delimiter). Verify by inspection that the `json_encode` fallback string reads cleanly.

## 2. Tests

- [ ] 2.1 Add `tests/ApiResponseTest.php` that defines the constants the helper needs (`CORE_PATH`, `IDENTIFIER_UID`), stubs `error_logs`, and runs `ApiResponse::Set()` in a subprocess (or a thin wrapper that captures output) for a known code and for an unknown code; assert the unknown code yields HTTP 500 with `code` `APP_INTERNAL_SERVER_ERROR` and no fatal. Verify `./vendor/bin/phpunit tests/ApiResponseTest.php` passes (the unknown-code case fatals before this group's change).
- [ ] 2.2 Add a case asserting that extra data does not override a reserved field (`code`, `http_code`, `message`, `meta`). Verify the test passes.

## 3. Integration verification

- [ ] 3.1 Run the full suite with `./vendor/bin/phpunit` (or `make test`) and confirm all tests pass, including existing tests that depend on the response envelope.
- [ ] 3.2 With `make up`, trigger a known error path (for example an invalid module) and confirm the response is well-formed JSON with the documented code, and that `logs/error.log` contains no fatal from `api_response.php`.
