# Implementation Plan: Expose the Resolved Legal Text Language

**Branch**: `007-manager-resolve-language` | **Date**: 2026-10-05 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/007-manager-resolve-language/spec.md` (GitHub issue #27)

## Summary

Consumers can currently only get the HTML of a legal text, not the language it was delivered in, so a silent fallback (English requested, German delivered) is invisible. We add `Erecht24Manager::resolve()` (and the facade `@method`) returning the existing `ResolvedLegalText` value object, extended with an optional `requestedLang` and a derived `isFallback()`. `LegalTextResolver` computes the normalized requested language once (explicit → app locale) and passes it along; its candidate order is unchanged. Purely additive (MINOR); docs in both READMEs and the CHANGELOG.

## Technical Context

**Language/Version**: PHP ^8.3 (CI: 8.3, 8.4; prefer-lowest/stable)

**Primary Dependencies**: Laravel 12/13 components (`illuminate/support`, `illuminate/filesystem`, `illuminate/view`); no new dependencies

**Storage**: Unchanged — Laravel filesystem disk via `LegalTextStore` (read-only for this feature)

**Testing**: Pest + Orchestra Testbench; `Storage::fake()`, `Http::preventStrayRequests()`

**Target Platform**: Laravel applications consuming the Composer package

**Project Type**: Library (Laravel package)

**Performance Goals**: No additional I/O versus `html()` — one resolution, same store reads

**Constraints**: Backward compatible (existing constructor calls and methods unchanged); PHPStan level 8; no HTTP in the read path

**Scale/Scope**: 4 source files touched (`ResolvedLegalText`, `LegalTextResolver`, `Erecht24Manager`, `Facades/ERecht24`), 2–3 test files, 2 READMEs, CHANGELOG

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Assessment | Status |
|---|---|---|
| I. Test-First with Pest | New tests in `tests/Unit/View/LegalTextResolverTest.php`, `tests/Feature/ManagerTest.php` (+ optional `tests/Unit/View/ResolvedLegalTextTest.php`) written before implementation; existing tests untouched | PASS |
| II. Static Analysis Gate | Fully typed signatures, no suppressions; `composer analyse` level 8 must pass | PASS |
| III. Consistent Code Style | `composer lint`; no formatting-only changes mixed in | PASS |
| IV. Backward-Compatible Public API | Additive only: new method, new facade docblock line, optional trailing constructor param; `ResolvedLegalText` documented as public API; CHANGELOG entry marks MINOR | PASS |
| V. Laravel Package Conventions | Exposed via existing `Erecht24Manager` singleton + `ERecht24` facade; no new bootstrapping or config | PASS |

**Post-design re-check (after Phase 1)**: All five gates still PASS — design artifacts introduce no new bindings, config, or breaking signatures.

## Project Structure

### Documentation (this feature)

```text
specs/007-manager-resolve-language/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/
│   └── public-api.md    # Phase 1 output
├── checklists/
│   └── requirements.md  # Spec quality checklist
└── tasks.md             # Phase 2 output (/speckit.tasks — not created here)
```

### Source Code (repository root)

```text
src/
├── Erecht24Manager.php          # + resolve()
├── Facades/
│   └── ERecht24.php             # + @method static ResolvedLegalText|null resolve(...)
└── View/
    ├── LegalTextResolver.php    # compute $requested once, pass to ResolvedLegalText (stays @internal)
    └── ResolvedLegalText.php    # + ?string $requestedLang = null, isFallback(), public-API docblock

tests/
├── Feature/
│   └── ManagerTest.php          # + resolve() scenarios (facade, enum/string, fallback, null, unknown type)
└── Unit/
    └── View/
        ├── LegalTextResolverTest.php   # + requestedLang / isFallback scenarios
        └── ResolvedLegalTextTest.php   # new: isFallback() truth table

README.md, README.de.md          # "Facade and Inertia" table row + example
CHANGELOG.md                     # Unreleased / Added
```

**Structure Decision**: Single-package layout already in place (`src/`, `tests/Unit`, `tests/Feature`); the feature only extends existing files plus one new unit test file.

## Implementation Approach

1. **Tests first** (red): add the scenarios from spec FR-012 and the derivation table in `data-model.md`.
2. **`ResolvedLegalText`**: add `public ?string $requestedLang = null` and `isFallback()`; docblock stating it is the public result of `Erecht24Manager::resolve()`.
3. **`LegalTextResolver::resolve()`**: after the candidates `try` block, `$requested = $this->normalize($lang) ?? $this->normalize(app()->getLocale());` and `new ResolvedLegalText($content, $candidate, $requested)`. `candidates()` unchanged.
4. **`Erecht24Manager::resolve()`**: `return $this->resolver->resolve($this->type($type), $lang);` with `@throws \InvalidArgumentException` docblock; import `ResolvedLegalText`.
5. **Facade**: add `@method static ResolvedLegalText|null resolve(LegalTextType|string $type, ?string $lang = null)` and the `use` import.
6. **Docs**: README EN/DE table row + short example (`lang`, `isFallback()`), CHANGELOG bullet.
7. **Gates**: `composer lint`, `composer analyse`, `composer test`.

## Risks

- **Reported vs. selected language mismatch**: if `requested` were derived differently from `candidates()`, fallback detection would be wrong. Mitigated by reusing `normalize()` and testing explicit-vs-locale and unconfigured cases.
- **Public API commitment**: `ResolvedLegalText` now falls under SemVer; future field changes must be additive.

## Complexity Tracking

No constitution violations — section intentionally empty.
