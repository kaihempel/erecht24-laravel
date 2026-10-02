# Data Model: eRecht24 API Client

**Spec**: [spec.md](./spec.md) | **Research**: [research.md](./research.md)

## LegalTextType (enum, backed by `string`)

| Case | Value | `fileSlug()` |
|---|---|---|
| `Imprint` | `imprint` | `imprint` |
| `PrivacyPolicy` | `privacyPolicy` | `privacy-policy` |
| `PrivacyPolicySocialMedia` | `privacyPolicySocialMedia` | `privacy-policy-social-media` |

`fileSlug()` returns a stable, file-name-safe identifier distinct from the wire value, for
consumers that cache/store legal text content locally (per FR-003).

Maps 1:1 to the three `GET` endpoints verified in research.md: `/imprint`, `/privacyPolicy`,
`/privacyPolicySocialMedia`.

## LegalText (readonly DTO)

| Field | Type | Source | Notes |
|---|---|---|---|
| `type` | `LegalTextType` | supplied by caller when constructing from a response | identifies which of the three documents this is |
| `htmlDe` | `?string` | API `html_de` | nullable — only `de`/`en` are ever present per existing `Erecht24Settings::SUPPORTED_LANGUAGES` |
| `htmlEn` | `?string` | API `html_en` | same as above |
| `created` | `?string` | API `created` | raw datetime string as provided by the API, not parsed into a `DateTime` (no timezone guarantee documented) |
| `modified` | `?string` | API `modified` | — |
| `pushed` | `?string` | API `pushed` | — |
| `warnings` | `?string` | API `warnings` | HTML string, may be empty |

Accessor: `html(string $language): ?string` — returns `htmlDe`/`htmlEn` by language code,
`null` if that language isn't present (per spec Edge Cases: absence of a language is not an
error).

**Validation rules**: None beyond type — this DTO is a pass-through read model; the API is the
source of truth for content.

## PushClient (readonly DTO)

| Field | Type | Required on create? | Source |
|---|---|---|---|
| `id` | `?int` | no (server-assigned) | API `client_id` |
| `pushMethod` | `string` | yes | API `push_method` (e.g. `GET`) |
| `pushUri` | `string` | yes | API `push_uri` |
| `cms` | `string` | yes | API `cms` |
| `cmsVersion` | `?string` | no | API `cms_version` |
| `pluginName` | `string` | yes | API `plugin_name` |
| `authorMail` | `?string` | no | API `author_mail` |
| `secret` | `?string` | n/a (response-only) | API `secret` — present after create/update, absent on list |

**Lifecycle / state transitions**:
- **Create**: caller builds a `PushClient` with no `id`/`secret`; `createClient()` returns a new
  `PushClient` with both populated.
- **Update**: caller supplies `id` plus updated fields; `updateClient()` returns a `PushClient`
  with a freshly issued `secret` (never the old one — see research.md "secret is never
  immutable").
- **Delete**: by `id` only; no return value. Deleting an `id` that doesn't exist (or isn't owned
  by the caller) raises `Erecht24ApiException` (404), not a silent no-op.
- **List**: returns `PushClient[]` without `secret` (not present in the `client` schema's
  `GET /clients` listing) — accessing `secret` on a listed instance is `null`.

**Business constraint**: Max 3 `PushClient` registrations per eRecht24 account (enforced
server-side; a 4th `createClient()` call surfaces `Erecht24ApiException` from the documented 403
quota response — see research.md "403 is NOT always an authentication failure").

## Erecht24ApiException (readonly fields, extends `\RuntimeException`)

| Field | Type | Notes |
|---|---|---|
| `status` | `int` | HTTP status code from the failing response |
| `apiMessage` | `?string` | the API's `message` field, when present; never includes `debug`, headers, or request body |

Constructed only from `status` + `apiMessage` — never from the raw `Illuminate\Http\Client\Response`
or request, so there is no path by which a header value (API key, plugin key) or a `PushClient`
secret can end up in `getMessage()`.

## Erecht24AuthenticationException (extends `Erecht24ApiException`)

Thrown only for `status === 401`. Same field set and the same no-credentials guarantee; kept as a
distinct subclass so consuming code can `catch (Erecht24AuthenticationException)` specifically
without string-matching on status codes.
