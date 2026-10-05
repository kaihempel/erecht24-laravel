# Data Model: Expose the Resolved Legal Text Language

No persistence changes. Storage layout (`<directory>/<fileSlug>.<lang>.html` + `.meta.json`) is untouched.

## ResolvedLegalText (value object, `KaiHempel\ERecht24\View\ResolvedLegalText`)

`final readonly`, now public API.

| Field | Type | Required | Description |
|---|---|---|---|
| `content` | `string` | yes | Stored legal text HTML (trusted eRecht24 output, non-empty after trim). |
| `lang` | `string` | yes | Delivered language: the configured language code whose stored file was returned (`de`, `en`). |
| `requestedLang` | `?string` | no (default `null`) | Normalized requested language: explicit `$lang` if given and non-empty, otherwise the app locale; lowercase primary subtag. May be a code that is not configured (e.g. `fr`). `null` = nothing requested. |

| Method | Returns | Rule |
|---|---|---|
| `isFallback()` | `bool` | `requestedLang !== null && requestedLang !== lang` |

### Validation / invariants

- Constructed only by `LegalTextResolver` inside the package; consumers may construct it (e.g. in tests) with two or three arguments.
- `lang` is always a configured language (guaranteed by resolver candidates).
- `requestedLang` is already normalized when set by the resolver; the value object itself does not normalize.

### Derivation table (configured `de,en`)

| Stored | Explicit `$lang` | App locale | `lang` | `requestedLang` | `isFallback()` |
|---|---|---|---|---|---|
| de, en | – | `en` | `en` | `en` | false |
| de | – | `en` | `de` | `en` | true |
| de | `en` | `de` | `de` | `en` | true |
| de, en | – | `en-GB` | `en` | `en` | false |
| de, en | `DE` | `en` | `de` | `de` | false |
| de, en | `fr` | `en` | `en` | `fr` | true |
| de, en | – | `''` | `de` | `null` | false |
| – | any | any | — | — | result is `null` |

## LegalTextType (enum, unchanged)

`imprint`, `privacyPolicy`, `privacyPolicySocialMedia`; string values accepted by the manager via `LegalTextType::tryFrom()`, otherwise `\InvalidArgumentException`.
