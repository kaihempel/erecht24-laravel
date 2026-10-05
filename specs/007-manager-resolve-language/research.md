# Research: Expose the Resolved Legal Text Language

No `NEEDS CLARIFICATION` items remained in the Technical Context. The decisions below record the design choices made while reading the current code (`src/View/LegalTextResolver.php`, `src/View/ResolvedLegalText.php`, `src/Erecht24Manager.php`, `src/Facades/ERecht24.php`).

## R1 — Where the requested language is carried

- **Decision**: Add an optional third promoted property `public ?string $requestedLang = null` to the existing `ResolvedLegalText` value object and a derived `isFallback(): bool`.
- **Rationale**: The resolver already returns this object to the Blade components and the manager; extending it keeps one result type. A defaulted trailing parameter keeps every existing `new ResolvedLegalText($content, $lang)` call (positional or named) valid → MINOR change (Constitution IV).
- **Alternatives considered**: (a) a new `LegalTextResult` DTO in `src/DTOs/` — duplicates the resolver result and needs mapping; (b) a separate `Erecht24Manager::resolvedLanguage()` method — requires two calls and a second resolution, racy against concurrent syncs; (c) returning an array — untyped, poor PHPStan support.

## R2 — How the requested language is computed

- **Decision**: In `LegalTextResolver::resolve()`, compute `$requested = $this->normalize($lang) ?? $this->normalize(app()->getLocale())` once before iterating candidates and pass it to every `ResolvedLegalText` constructed. Leave `candidates()` unchanged.
- **Rationale**: Reuses the existing `normalize()` (lowercase primary subtag, `en-GB`/`en_GB` → `en`, empty → null) so the reported requested language matches what selection used. `candidates()` must keep its current order (explicit → locale → first configured → rest) including the case where an explicit language is unconfigured and the locale then wins; changing it would alter `html()` behavior (FR-004, FR-007).
- **Note**: The requested language is not filtered by the configured languages. An unconfigured request (`fr`) is reported as `fr` and yields `isFallback() === true` (spec assumption).
- **Alternatives considered**: Deriving the requested language from the first candidate — wrong for unconfigured requests (they are filtered out of candidates), so a fallback would go undetected.

## R3 — Fallback semantics

- **Decision**: `isFallback()` returns `$this->requestedLang !== null && $this->requestedLang !== $this->lang`.
- **Rationale**: Exactly matches FR-002; `null` means "nothing was requested", which can never be a fallback. Kept as a method (not a stored property) so it cannot become inconsistent with `lang`/`requestedLang`.

## R4 — Public API visibility

- **Decision**: `ResolvedLegalText` becomes documented public API (it currently carries no `@internal` tag, so no tag needs removing; add a short class docblock describing it as the public result of `Erecht24Manager::resolve()`). `LegalTextResolver` keeps `@internal`.
- **Rationale**: Constitution IV treats any non-internal class under `src/` as public; documenting it makes the SemVer commitment explicit. The namespace stays `KaiHempel\ERecht24\View` to avoid a breaking move.
- **Alternatives considered**: Moving the class to `DTOs/` — would break existing type references; not worth it for an additive change.

## R5 — Manager and facade surface

- **Decision**: `Erecht24Manager::resolve(LegalTextType|string $type, ?string $lang = null): ?ResolvedLegalText` returning `$this->resolver->resolve($this->type($type), $lang)`; add the matching `@method static` line to `Facades\ERecht24`.
- **Rationale**: Mirrors `html()`/`has()` exactly, reuses `type()` for the `\InvalidArgumentException` on unknown strings (FR-005) and goes through the existing facade (Constitution V). The store-only path guarantees no HTTP (FR-006).
- **Optional refactor (not required)**: `html()`/`has()` could delegate to `resolve()`; allowed only if existing tests stay unchanged.

## R6 — Testing approach

- **Decision**: Pest tests in `tests/Unit/View/LegalTextResolverTest.php` (requested language, fallback flag, regional locale, explicit-vs-locale, unconfigured request, no locale) and `tests/Feature/ManagerTest.php` (facade `resolve()` with enum/string, only-`de` fallback, nothing stored → null, unknown type → exception). `ManagerTest` already calls `Http::preventStrayRequests()` in `beforeEach`, which satisfies SC-004. A unit test for `ResolvedLegalText::isFallback()` covers the null and equal cases directly.
- **Rationale**: Constitution I; existing tests remain untouched (SC-003).

## R7 — Documentation

- **Decision**: Add a `resolve()` row to the method tables in `README.md` and `README.de.md` ("Facade and Inertia" / "Facade und Inertia"), plus a short example passing `lang` and `isFallback()` into Inertia props; add a CHANGELOG "Unreleased / Added" bullet noting the MINOR, additive nature.
- **Rationale**: FR-011; READMEs must stay content-equivalent (CLAUDE.md).
