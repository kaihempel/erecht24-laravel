# Research: Seal rendering

## Resolver location and shape
- **Decision**: `final class SealResolver` in `src/View/`, singleton, ctor-injected `Erecht24Settings`, method `for(LegalTextType, string $lang): ?LegalTextSeal`.
- **Rationale**: Mirrors `LegalTextResolver`; keeps config access inside `Erecht24Settings`.
- **Alternatives**: Putting logic in the component (rejected: duplicated across components, harder to unit test); adding a ctor parameter (rejected: breaks public constructors).

## Getting the resolver into the component
- **Decision**: `app(SealResolver::class)` inside `render()`.
- **Rationale**: Constructor signatures are a public contract (Constitution IV, FR-011).
- **Alternatives**: Constructor injection (breaking); facade/manager (unnecessary).

## Passing the seal to views
- **Decision**: Always add `seal` (nullable) to the data array; dedicated views forward it; shared view guards with `! empty($seal)`.
- **Rationale**: Published views that ignore `$seal` keep working; when absent the `@if` emits no output. Blade `@if` block placed so that with no seal the output stays byte-identical (verify whitespace in a snapshot-style test comparing to the pre-change markup).
- **Alternatives**: View composer (rejected: hidden coupling, harder to test).

## Styling
- **Decision**: Inline `text-align:center` on wrapper div and `width:{displayWidth}px; max-width:100%; height:auto` on the image, plus `width`/`height` attributes and `loading="lazy"`.
- **Rationale**: Works without a CSS framework; attributes avoid layout shift; `max-width` prevents overflow.

## URL generation
- **Decision**: `asset('vendor/erecht24/'.$file)`.
- **Rationale**: Honors `ASSET_URL`; matches the location used by asset publishing (issue #2).

## Alt text
- **Decision**: Static map keyed by type and `de`/`en`; any other language falls back to English (only `de`/`en` are supported by the package).

## Invalid config
- **Decision**: Let `Erecht24Settings` throw `InvalidConfigurationException`; the view layer wraps it in `ViewException`. Seal is resolved only after a text resolved, so missing-text pages are unaffected.

## Open items
- None. Image files exist in `public/`; publishing them is issue #2 and outside this plan. No `update-agent-context.sh` exists in `.specify/scripts/bash`, so agent context is updated manually in `CLAUDE.md`.
