# Proposal

## Why

`ApiResponse::Set()` builds a proper fallback when a response code is unknown, then immediately overwrites it with `$codes->$code` unconditionally. When the code is missing (or the YAML failed to parse) that assignment is `null`, and the following `http_response_code($response->http_code)` fatals on `null` in PHP 8. The result: instead of a controlled `APP_INTERNAL_SERVER_ERROR` response, an unknown code crashes the request. The log path in the same file also concatenates `ERROR_LOG_FILE` to a message without a separator, producing a garbled log line.

## What Changes

- The unknown-code path stops overwriting the fallback, so a missing code returns the documented internal-error envelope with HTTP 500.
- The log message concatenation is fixed so the log file path is separated from the message text.
- Observable behavior: any `ApiResponse::Set()` call with an unknown code returns a well-formed JSON error instead of a fatal.
- No changes to the response codes themselves or to `api_codes.yml`.
- **BREAKING**: none.

## Capabilities

### New Capabilities
- `api-response-envelope`: how the API renders a response for a known or unknown code, including the fallback and metadata.

### Modified Capabilities
- Ninguna.

## Impact

- `app/core/helpers/api_response.php`.
- Affects every endpoint, since all responses flow through this helper.
- No schema, configuration, or dependency changes.
