# Tasks

## 1. Fix the mail helper

- [ ] 1.1 In `app/core/helpers/phpmailer.php`, guard `cc`, `bcc`, `attachments`, and `reply_to` so each is applied only when present and non-empty. Verify `php -l` passes and a payload with only `from`, `to`, `subject`, `html_body`, `text_body` no longer references absent keys.
- [ ] 1.2 Configure the transport from `$_apiConfig->mailing` (host, port, security, user, password, sender) instead of `json_decode($_apiConfig->smtp_auth)`. Verify `php -l` passes and `rg "smtp_auth" app/core/helpers/phpmailer.php` no longer matches a `json_decode` call.
- [ ] 1.3 Remove the `utf8_decode()` calls, set `CharSet` to UTF-8, and apply the sender name correctly. Verify `rg "utf8_decode" app/core/helpers/phpmailer.php` returns no matches.
- [ ] 1.4 Add `tests/MailerTest.php` asserting the helper assembles a message from the `mailing` config section, tolerates absent optional fields, and signals failure via `AppException(903000)` when the transport rejects the message (using a stubbed transport). Verify `./vendor/bin/phpunit tests/MailerTest.php` passes.

## 2. Fix the caller

- [ ] 2.1 In `app/core/modules/access/local_login.php`, build `from` as `['email' => ..., 'name' => ...]` so the helper reads a real sender name. Verify `php -l` passes and the array has a `name` key.
- [ ] 2.2 Confirm the recovery flow still builds the same URL and template variables, and document the mailer parameter contract in the project docs. Verify the documented example matches the helper's accepted keys.

## 3. Integration verification

- [ ] 3.1 Run the full suite with `./vendor/bin/phpunit` (or `make test`) and confirm all tests pass.
- [ ] 3.2 With `make up`, trigger the login-recovery flow and confirm the message is captured by the Mailpit service with the correct sender name, UTF-8 subject, and body, and that no mailer warning appears in `logs/error.log`.
