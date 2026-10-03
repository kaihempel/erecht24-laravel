# Data Model: Blade Views and Component

No new persisted data. Read-only over existing store files `<directory>/<fileSlug>.<lang>.html`.

## LegalTextResolver (internal, `KaiHempel\ERecht24\View`)
- **Input**: `LegalTextType $type`, `?string $lang`
- **Output**: `?ResolvedLegalText` — readonly value with `string $content`, `string $lang`; `null` when nothing is available.
- **Rules**:
  1. Candidates = [`$lang`, `app()->getLocale()`, first configured language], each normalized to primary subtag (lowercase), kept only if configured; deduplicated.
  2. First candidate with non-blank stored content wins.
  3. Otherwise first configured language (config order) with non-blank content wins.
  4. Any store read failure on a language is treated as "missing" for that language.
  5. No content anywhere → `null`.

## Components
| Class | Type | View | Attributes |
|---|---|---|---|
| `LegalText` | from `type` attr | wrapper / `missing` | `type` (required), `lang` (optional) |
| `Imprint` | `LegalTextType::Imprint` | `erecht24::imprint` | `lang` |
| `PrivacyPolicy` | `LegalTextType::PrivacyPolicy` | `erecht24::privacy-policy` | `lang` |
| `PrivacyPolicySocialMedia` | `LegalTextType::PrivacyPolicySocialMedia` | `erecht24::privacy-policy-social-media` | `lang` |

## View variables
- Wrapper/thin views: `$content` (string, raw HTML), `$type` (`LegalTextType`), `$lang` (resolved language).
- `missing`: `$type`; debug flag read via `config('app.debug')`.
