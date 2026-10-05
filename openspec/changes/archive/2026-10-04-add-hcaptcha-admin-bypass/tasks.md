# Tasks

## 1. Add the bypass column to the schema

- [x] 1.1 Create `resources/migrations/hcaptcha_bypass.sql` adding a nullable
  `hcaptcha_bypass VARCHAR(255) DEFAULT NULL` column to the users table (same
  style as `resources/migrations/rate_limits.sql`). Verify the file exists and
  the statement parses by applying it to a scratch database (`mysql < ...`).
- [x] 1.2 Add the same column to the `dev_users` `CREATE TABLE` in
  `resources/karewa_dev.sql` and `app/karewa_dev_dev.sql`. Verify the column
  appears in both files' users-table definitions with `rg "hcaptcha_bypass" resources/karewa_dev.sql app/karewa_dev_dev.sql`.
- [x] 1.3 Add `hcaptcha_bypass` to `$moduleFields` in
  `app/core/modules/users/controller.php` with `listed => false`,
  `filter => false`, `saved => false`, and add it to the `USER_COLUMNS`
  constant in `tests/UsersFieldMappingTest.php`. Verify
  `./vendor/bin/phpunit tests/UsersFieldMappingTest.php` passes and the users
  endpoints never return the column.

## 2. Make hCaptcha verification fail closed

- [x] 2.1 In `app/core/helpers/hcaptcha.php`, accept the upstream call only when
  the body decodes to an object with `success === true`; otherwise throw
  `905000` on transport failure, empty/undecodable body, or non-success flag,
  without dereferencing a null body. Verify `php -l app/core/helpers/hcaptcha.php`
  passes.
- [x] 2.2 Add `tests/HCaptchaVerificationTest.php` covering a non-success
  response, a malformed/empty body, and a transport failure, each expecting
  `905000` and no unhandled error. Verify
  `./vendor/bin/phpunit tests/HCaptchaVerificationTest.php` passes. Refactor the
  response-handling seam (e.g. an injectable response/parser) only as far as the
  test needs.

## 3. Administrator-only secret generation and rotation

- [x] 3.1 Register the generation route in `app/core/bootstrap/routes.php` and
  add the module with an `index.php` declaring `store => [true, [1]]` so
  `ModuleHandler::Validate()` gates it. Verify an administrator (`USER_ROLE 1`)
  can call it and a non-administrator is rejected with `901004`.
- [x] 3.2 Implement generation in the module controller: create a
  `random_bytes(32)` secret, store `password_hash(secret, PASSWORD_DEFAULT)` in
  `users.hcaptcha_bypass` for `USER_ID`, and return the base64url plaintext once.
  Verify the stored value is a hash (not the plaintext) and the response contains
  the plaintext.
- [x] 3.3 Make generation replace any existing hash so only the latest secret is
  honored. Verify a login with the previous secret no longer bypasses while the
  new one does.
- [x] 3.4 Document the mechanism in `README.md`: the `X-HCaptcha-Bypass` header,
  the administrator-only scope, how to obtain/rotate the secret, and a warning to
  use it only for testing/trusted clients. Verify the documented header and steps
  match the implemented endpoint and behavior.

## 4. Apply the bypass at login

- [x] 4.1 In `app/core/modules/access/local_login.php`, resolve the enabled user
  by email before captcha verification, then skip `HCaptcha::Validate()` only
  when the `X-HCaptcha-Bypass` header is present, the user is `role_id = 1`, and
  the header value verifies against the stored hash. Verify the bypass scenarios
  in `hcaptcha-bypass/spec.md` (admin + matching secret skips; wrong secret,
  unknown user, or non-admin still requires captcha).
- [x] 4.2 Keep every failure returning the generic authentication error and keep
  the field-validation responses unchanged. Verify a bypass attempt with a bad
  secret for an existing admin returns the same generic error as a bad password.
- [x] 4.3 Add a test for the bypass decision (present/matching, present/wrong,
  absent, non-admin) against the extracted decision seam. Verify
  `./vendor/bin/phpunit tests/` passes for the new test file.

## 5. Integration verification

- [x] 5.1 Run the full suite with `./vendor/bin/phpunit` (or `make test`) and
  confirm all tests pass.
- [x] 5.2 With `make up` and prefix `dev_`: generate a secret for an
  administrator, then `POST /api/v5/access` from Postman (or `curl`) with the
  `X-HCaptcha-Bypass` header and no captcha token, and confirm login succeeds;
  confirm a non-administrator without the header still fails captcha and that
  `users.hcaptcha_bypass` holds only a hash.
