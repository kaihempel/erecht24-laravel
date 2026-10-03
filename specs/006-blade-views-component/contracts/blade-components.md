# Contract: Blade Components and Views

## Tags
```blade
<x-erecht24::legal-text type="imprint" lang="de" />
<x-erecht24::imprint />
<x-erecht24::privacy-policy lang="en" />
<x-erecht24::privacy-policy-social-media />
```
- `type`: `imprint | privacyPolicy | privacyPolicySocialMedia | privacy-policy | privacy-policy-social-media`; invalid → `\InvalidArgumentException` naming allowed values.
- `lang`: optional; `de | en` (regional forms like `de_DE` accepted, reduced to `de`).
- Extra HTML attributes are forwarded to the wrapper (`$attributes`), e.g. `class="prose"`.

## Behavior
- Output = wrapper with stored HTML unescaped; no HTTP, no exceptions for missing text.
- Nothing stored → `erecht24::missing`; sync hint only if `config('app.debug')` is true.

## Views (namespace `erecht24`)
`components.legal-text`, `imprint`, `privacy-policy`, `privacy-policy-social-media`, `missing`.

## Publishing
`php artisan vendor:publish --tag=erecht24-views` → `resources/views/vendor/erecht24/`. Published copies override package views.

## Security note (documented in wrapper)
`{!! $content !!}` is intentionally unescaped; content originates from the trusted eRecht24 API and is not sanitized.
