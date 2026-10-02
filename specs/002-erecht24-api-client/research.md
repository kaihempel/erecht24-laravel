# Research: eRecht24 API Client

**Spec**: [spec.md](./spec.md) | **Date**: 2026-10-02

## Source of truth

The Swagger UI at https://api-docs.e-recht24.de/ is a static SPA shell; it loads its actual
OpenAPI document client-side from:

```
https://api.e-recht24.de/v2openapi.json?v=202204191032
```

(found via the inline bootstrap script in the docs page: `url = "//" + host.replace('api-docs.', 'api.') + '/v2openapi.json?...'`).
This document (OpenAPI 3.0.0, title "eRecht24.de API v2 (current)") is the verified source for
all paths, methods, headers, and schemas below. Server base path: `/v2`, consistent with this
package's existing `base_url` config default (`https://api.e-recht24.de/v2`).

## Verified endpoint catalogue

| Method | Path | Purpose | Success | Documented failure codes |
|---|---|---|---|---|
| GET | `/clients` | List registered push clients | 200: array of `client` (may be empty) | 401, 503 |
| POST | `/clients` | Register a new push client | 200: `{ client_id, secret }` | 400, 401, 403 (quota), 409 (duplicate URL), 422, 503 |
| PUT | `/clients/{client_id}` | Update a push client's details | 200: `{ secret }` (always a fresh one) | 401, 404, 422, 503 |
| DELETE | `/clients/{client_id}` | Delete a push client | 200: `[]` | 401, 404, 503 |
| POST | `/clients/{client_id}/testPush?type=...` | Fire a test push (`type` ∈ ping, message, imprint, privacyPolicy, privacyPolicySocialMedia) | 200: `[]` | 401, 404, 496, 497, 498, 503 |
| GET | `/imprint` | Fetch current imprint | 200: `imprint` (html_de, html_en, created, modified, pushed, warnings) | 401, 404, 503 |
| GET | `/privacyPolicy` | Fetch current privacy policy | 200: `privacyPolicy` (same shape as imprint) | 401, 404, 503 |
| GET | `/privacyPolicySocialMedia` | Fetch current social-media privacy policy | 200: `privacyPolicySocialMedia` (same shape as imprint) | 401, 404, 503 |

Not used by this feature (out of scope per issue #4): `GET /message`, `POST /push/{type}` (eRecht24-initiated push *to* the consumer, not something this client calls).

### Auth headers (verified via `components.securitySchemes`)

- `eRecht24-api-key` — header, required on every request.
- `eRecht24-plugin-key` — header, required on every request.

Both schemes also accept the key via query string per the spec, but header is prioritized and is
what this client uses exclusively (query param would risk the key leaking into access logs/URLs).

### `client` schema (push client request/response body)

Fields: `client_id` (int64, response-only), `project_id` (int64, response-only), `push_method`
(string, e.g. `GET`), `push_uri` (string URL), `cms` (string), `cms_version` (string, optional),
`plugin_name` (string), `author_mail` (string, optional), `created_at` / `updated_at` (response-only
timestamps).

### Legal text schema (`imprint`, `privacyPolicy`, `privacyPolicySocialMedia` — identical shape)

Fields: `html_de`, `html_en` (per-language HTML strings — only `de`/`en` are ever present, matching
this package's existing `SUPPORTED_LANGUAGES`), `created`, `modified`, `pushed` (datetime strings),
`warnings` (HTML string, may be empty).

### Error body shape (401/403/404/409/422/503/496/497/498)

All error responses share one shape: `message` (English), `message_de` (German), `faq_link`,
`error_code` (int), and sometimes `debug` (object, e.g. validation `param_name` details on 422).
None of these fields ever carry back request credentials — confirmed by example payloads.

## Resolved decisions

### Decision: 403 is NOT always an authentication failure
**Rationale**: The issue text says "401/403 → Erecht24AuthenticationException", but the verified
spec shows `POST /clients` uses 403 to mean "too many clients already registered for this
project" — a quota/business-rule rejection, unrelated to credentials. No other endpoint documents
403 at all. Mapping it to an authentication exception would actively mislead consumers debugging a
quota issue.
**Decision**: Only 401 maps to `Erecht24AuthenticationException`. 403 (and all other failing
statuses) map to `Erecht24ApiException`, carrying the status code and the API's `message`.
**Alternatives considered**: Mapping 403 to the auth exception as the issue literally states —
rejected because it is factually wrong for this API and would misdirect error handling in
consuming applications.

### Decision: push client secret is never immutable — update always re-issues it
**Rationale**: `PUT /clients/{client_id}` response schema only contains `secret`, and its
description explicitly states "the client will receive a secret, which must be stored."
**Decision**: `updateClient(PushClient): PushClient` returns a `PushClient` whose `secret` is the
freshly issued one; the caller is expected to persist it, replacing any previously stored secret.
**Alternatives considered**: Treating the secret as immutable after creation — rejected; this was
an earlier spec clarification answer made before endpoint verification, now corrected (see
spec.md Clarifications section).

### Decision: delete of a non-existent/foreign client_id is NOT idempotent
**Rationale**: `DELETE /clients/{client_id}` documents 404 "Client could not be deleted. Maybe
there is no client with the given client_id or you do not have the permission to delete it."
**Decision**: `deleteClient(int): void` surfaces `Erecht24ApiException` (status 404) for an
unknown/foreign client_id rather than succeeding silently.
**Alternatives considered**: Idempotent delete (swallow 404) — rejected for the same reason as
above; corrected after verification.

### Decision: `listClients()` returns a single unpaginated array
**Rationale**: `GET /clients` response schema is a plain JSON array with no pagination envelope,
and the API caps each account at 3 registered clients (per `DELETE` endpoint description: "the
number of clients per API-Key is limited to 3").
**Decision**: No pagination handling needed; `listClients(): array` maps the response array
directly to `PushClient[]`.

### Decision: retry policy covers connection errors, 5xx, and 429
**Rationale**: Laravel's HTTP client `retry()` operates on exceptions/response predicates
independent of the documented OpenAPI status codes (429 is not in eRecht24's documented set today,
but defending against it is cheap and matches standard HTTP semantics for rate limiting).
**Decision**: Build the `PendingRequest` with `->retry(times: 3, sleepMilliseconds: 200, when:
fn ($exception) => $exception instanceof ConnectionException || ($exception instanceof
RequestException && ($exception->response->serverError() || $exception->response->status() ===
429)), throw: false)`, then inspect the final response manually to decide which exception to
throw. 4xx (other than 429) must short-circuit the retry predicate.
**Alternatives considered**: Retrying all 4xx — rejected, wastes attempts on requests that cannot
succeed by retrying (validation errors, 404s, quota 403).

### Decision: `fireTestPush` default `type` value
**Rationale**: The issue's example signature suggests `string $type = 'ping'`, but the verified
API's query parameter schema default is `imprint`. `ping` is still a valid enum value (and the
most side-effect-free choice for a connectivity check against a client's `pong` handler).
**Decision**: Keep `fireTestPush(int $clientId, string $type = 'ping')` as specified in the issue
— `ping` is explicitly documented as a supported enum value (`ping`, `message`, `imprint`,
`privacyPolicy`, `privacyPolicySocialMedia`) and is the most conservative default for a "did my
endpoint come up" check; the API's own default (`imprint`) is just what the Swagger UI
pre-fills, not evidence that `ping` is wrong.

### Decision: HTTP client base configuration
**Rationale**: Config already provides `base_url` (default `https://api.e-recht24.de/v2`, matching
the verified server path) and `timeout` via `Erecht24Settings`.
**Decision**: Build one preconfigured `Illuminate\Http\Client\PendingRequest` via
`Http::baseUrl($settings->baseUrl())->acceptJson()->timeout($settings->timeout())
->withHeaders(['eRecht24-api-key' => ..., 'eRecht24-plugin-key' => ...])`, resolved once per
`Erecht24Client` instance (itself a container singleton) so Testbench/`Http::fake()` can intercept
it in tests via the facade as usual.

### Decision: secrets never appear in exceptions or logs
**Rationale**: FR-014 / constitution Principle II (no silent suppression) and the legal/
reputational sensitivity noted in the constitution's rationale for Principle I.
**Decision**: Exception constructors accept only status code and the API's `message`/`message_de`
(never the raw request, headers, or response body wholesale); request-building code never
interpolates key/secret values into log statements, and tests assert this directly (per issue
acceptance criteria) using `Http::fake()` response inspection + log spies.
