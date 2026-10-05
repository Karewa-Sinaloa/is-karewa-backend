# Design

## Context

`app/config.yml` provides the API base in two shapes:

```yaml
api_url: https://kapi.chavodigital.com/api/v5/   # top-level string
api:
  url: kapi.chavodigital.com/api/v5/             # host + path, no scheme
  https: true
```

`base.php` currently does `define('API_URL', $_config->api)`, which stores the
`api` map (a `stdClass`) rather than a URL. Only one consumer exists today:
`loginMailer` in `app/core/modules/access/local_login.php`, which concatenates a
relative path onto `API_URL`.

## Goals / Non-Goals

- Goal: `API_URL` is a usable absolute URL string.
- Non-goal: the mailer transport, optional-field handling, charset, and sender
  shape, owned by `fix-mailer-delivery`.
- Non-goal: changing the configuration schema.

## Decisions

- **Build `API_URL` from `api.url` plus the scheme from `api.https`.** This keeps
  the existing `api` node as the source of truth and produces
  `https://kapi.chavodigital.com/api/v5/`. Scheme is chosen as `https` when
  `api.https` is truthy, otherwise `http`. A trailing slash is preserved so
  concatenated relative paths line up with the current behavior.
- **Fallback to a configured absolute URL if the `api` node is not a map.**
  Defensively handle the case where `api` is already a string (older configs) or
  absent, so the constant is always a string. Alternative rejected: reading the
  top-level `api_url` unconditionally, which would change the documented source of
  truth.

## Risks / Trade-offs

- If `api.url` already includes a scheme, prepending one would double it →
  Mitigation: detect an existing `://` and use the value as-is.
- Trailing-slash differences could change the recovery URL → Mitigation: keep a
  single trailing slash, matching the current expected shape.

## Migration Plan

- Code-only change; no data migration.
- Rollback: revert the `API_URL` definition.

## Open Questions

- None.
