# Research: Blade Views and Component

## Decision 1: Component registration
- **Decision**: `Blade::componentNamespace('KaiHempel\\ERecht24\\View\\Components', 'erecht24')` in the provider's `boot()`; views via `loadViewsFrom(resources/views, 'erecht24')`.
- **Rationale**: Gives `<x-erecht24::legal-text>`, `<x-erecht24::imprint>` (kebab-case class names → `privacy-policy-social-media`) with no per-component registration. Anonymous wrapper at `components/legal-text.blade.php` is addressed as `erecht24::components.legal-text`.
- **Alternatives**: `loadViewComponentsFrom` (prefix-style `<x-erecht24-imprint>`) — rejected, issue demands `::` syntax.

## Decision 2: Class component vs wrapper view
- **Decision**: `LegalText` class component renders `erecht24::components.legal-text` (wrapper) passing `$content`; convenience classes extend it with a fixed type and render their thin view (`erecht24::imprint` etc.), which `@include`s the wrapper. Fallback renders `erecht24::missing`.
- **Rationale**: Matches issue ("thin views include the wrapper"); one customization point (wrapper) plus per-type override.
- **Note**: The wrapper file lives in `components/` but is used via include/`view()`, not as an anonymous tag, to avoid a name clash with the class component `legal-text`.

## Decision 3: Type attribute values
- **Decision**: Generic `type` accepts the `LegalTextType` wire value (`imprint`, `privacyPolicy`, `privacyPolicySocialMedia`) and the file slug (`privacy-policy`, `privacy-policy-social-media`); anything else throws `\InvalidArgumentException` listing allowed values (FR-012).
- **Rationale**: Wire values are the existing public identifiers; slugs are what the issue's view names use and read naturally in Blade.
- **Alternatives**: Wire values only — less ergonomic; slugs only — diverges from enum.

## Decision 4: Language resolution and store safety
- **Decision**: Candidates in order: `lang` attribute, `app()->getLocale()`, first configured language. Each candidate is reduced to its primary subtag (`strtolower`, split on `_`/`-`) and kept only if in `Erecht24Settings::languages()`. Walk candidates, return first with stored content; else iterate configured languages in order and return first stored. Empty/whitespace-only content counts as missing.
- **Rationale**: `LegalTextStore` throws `\InvalidArgumentException` for unconfigured languages, so filtering first prevents exceptions. Satisfies spec clarification (`de_DE` → `de`).

## Decision 5: Never throw on missing/unreadable text
- **Decision**: Wrap store reads in the resolver; `LegalTextStoreException`/filesystem `Throwable` during read → treated as missing → fallback view. Invalid `type` is the only developer-facing exception.
- **Rationale**: FR-007; public page must not crash.

## Decision 6: Fallback hint gating
- **Decision**: `missing.blade.php` shows the `php artisan erecht24:sync` hint under `@if (config('app.debug'))`. Message text neutral and English (overridable via published views); resolves deferred item from clarify.
- **Alternatives**: Translation files — deferred, out of scope.

## Decision 7: Dependencies
- **Decision**: Add `"illuminate/view": "^12.0|^13.0"` to `require`.
- **Rationale**: Package now uses Blade directly; currently only transitively present.

## Decision 8: Agent context update
- `.specify/scripts/bash/update-agent-context.sh` does not exist in this repo; step skipped. No new technology beyond Laravel Blade.
