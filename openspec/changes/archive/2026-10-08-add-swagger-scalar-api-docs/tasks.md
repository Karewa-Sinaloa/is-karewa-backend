## 1. Specification and contract

- [x] 1.1 Review the OpenAPI shape for all current API routes and verify the published contract covers public and protected endpoints.
- [x] 1.2 Define the versioning strategy for `api/docs/openapi.json` and verify the chosen version appears in the spec metadata.
- [x] 1.3 Document query syntax, filters, grouping, sorting, field selection, and internal fields based on `BaseModel` and each module's `$moduleFields`.

## 2. Documentation publishing

- [x] 2.1 Add the public Scalar documentation surface and verify an anonymous user can open it without authentication.
- [x] 2.2 Wire the docs UI to the static JSON artifact and verify it loads `api/docs/openapi.json` successfully.
- [x] 2.3 Regenerate the OpenAPI JSON from real controllers and verify the public copy stays synchronized.

## 3. Static delivery and future portability

- [x] 3.1 Ensure the OpenAPI JSON is committed as a static, versioned file and verify it exists at `api/docs/openapi.json`.
- [x] 3.2 Validate the published docs structure remains portable for future GitHub Pages hosting and verify no runtime-only coupling is required.
- [x] 3.3 Add concrete request/response examples per endpoint family so module docs are easier to consume.
- [x] 3.4 Add documented query param examples for pagination, sorting, grouping, field selection, search, filters, and pagination embedding.
- [x] 3.5 Add representative error response examples sourced from the project API codes file.
