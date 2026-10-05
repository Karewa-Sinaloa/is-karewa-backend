# Tasks

## 1. Align the access module field map

- [x] 1.1 In `app/core/modules/access/local_login.php`, change the
  `recovery_date` field mapping from `'field' => 'recovery_datetime'` to
  `'field' => 'recovery_date'`. Verify `php -l` passes.

## 2. Fix the recovery write

- [x] 2.1 In `Recovery()`, change the update field `['recovery_datetime',
  $rec_date]` to `['recovery_date', $rec_date]`. Verify
  `rg "recovery_datetime" app/core/modules/access/` returns no matches.

## 3. Fix the reset read and write

- [x] 3.1 In `Reset()`, change the SELECT field list from `'recovery_datetime'` to
  `'recovery_date'` and read the expiration from `$data['recovery_date']`.
- [x] 3.2 In `Reset()`, change the update field `['recovery_datetime', NULL]` to
  `['recovery_date', NULL]` and remove the `['email_verified', 1]` entry. Verify
  neither `recovery_datetime` nor `email_verified` appear in
  `app/core/modules/access/local_login.php`.

## 4. Tests and documentation

- [x] 4.1 Add `tests/AccessFieldMappingTest.php` asserting that
  `local_login.php` references only `recovery_date` (no `recovery_datetime`) and
  contains no `email_verified`, and that its `$moduleFields` maps `recovery_date`
  to the `recovery_date` column. Verify
  `./vendor/bin/phpunit tests/AccessFieldMappingTest.php` passes.
- [x] 4.2 Update `orm_wiki.md` if it documents the access recovery fields, so the
  documented names match the corrected code; otherwise note the correction in the
  change only. (Not documented there; no doc change needed.)

## 5. Integration verification

- [x] 5.1 Run the full suite with `./vendor/bin/phpunit` (or `make test`) and
  confirm all tests pass.
- [x] 5.2 With `make up` and prefix `dev_`, exercise `POST
  /api/v5/access/recovery` with an active user email and confirm no `Unknown
  column 'recovery_datetime'` entry appears in `logs/error.log`/`logs/debug.log`.
