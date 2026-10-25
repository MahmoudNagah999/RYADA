# ADR-005: API conventions

- **Status**: Accepted
- **Date**: 2026-10-25
- **Deciders:** Mahmoud Nagah
- **Related issues:** #10

## Context

The club API is consumed by a mobile/web client built by a separate developer.
It needs one predictable contract: stable URLs, one error shape, and protection
against abuse on public endpoints.

## Decision

### Versioning

- Every route lives under `/api/vN/`. Each version has its own file in `routes/api/`
  (`v1.php`), included from `routes/api.php`. A new major version is a new file and
  a new group; v1 keeps working.
- A test fails if any `api/` route is not under a version prefix.

### Success responses

- Single resources and collections go through API Resources and are wrapped in `data`.
- Extra information goes in `meta` (for example the token on login). Paginated
  collections use Laravel's `data`, `links` and `meta`.
- Dates are ISO 8601 UTC (ADR-003).

### Error responses

- Every error under `api/*` has the shape `{"code": "...", "message": "..."}`.
  Clients branch on `code`; `message` is translated (ADR-003) and meant for humans.
- Validation errors (422) add `errors`, keyed by field.
- Rendering lives in one place: `App\Http\Errors\ApiExceptionRenderer`, called from
  `bootstrap/app.php`. Messages are flat keys `errors.<code>` in `lang/*/errors.php`.

| Status | Code |
|---|---|
| 401 | `http.unauthenticated` |
| 403 | `http.forbidden` |
| 404 | `http.not_found` |
| 405 | `http.method_not_allowed` |
| 422 | `validation.failed` |
| 429 | `http.too_many_requests` (with `Retry-After`) |
| 500 | `server.error` (no internal details unless `APP_DEBUG=true`) |
| any | domain codes from `BusinessRuleException`, e.g. `auth.invalid_credentials` |

- `SetLocale` is a global middleware, so 401, 404 and 405 (thrown before route
  middleware runs) are translated too.

### Rate limiting

| Limiter | Applied to | Limit | Key |
|---|---|---|---|
| `api` | every `/api/vN` route | 120/min authenticated, 60/min guests | user id, or IP |
| `auth` | `login` | 5/min, and 20/min per IP overall | email + IP, and IP |
| `register` | `register-club` | 10/hour | IP |

- Limiters are defined in `AppServiceProvider`. A test fails if an API route misses `throttle:api`.

## Consequences

- A new public endpoint that can be abused (password reset, invitations, etc.) needs its
  own named limiter.
- Rate limits use `$request->ip()`. Behind a proxy or load balancer, trusted proxies must
  be configured or every client shares one IP. This is done with Docker in #36.
- With Redis (#29) the limiters move to Redis with no code change.
- Adding an error means adding the key to both `en` and `ar` (enforced by `LangFilesTest`).
