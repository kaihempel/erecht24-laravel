# Implementation Plan: Render the trust seal below imprint and privacy policy

**Branch**: `013-seal-rendering` | **Date**: 2026-10-30 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/013-seal-rendering/spec.md` (GitHub issue #61)

## Summary

Add a `SealResolver` singleton that maps (`LegalTextType`, language) to an optional immutable `LegalTextSeal` DTO using the existing `Erecht24Settings::imprintSeal()` / `privacyPolicySeal()` accessors (spec 012). `AbstractLegalTextComponent::render()` passes the result as `seal` to the content view; the shared `components/legal-text.blade.php` renders a centered, inline-styled `<img>` inside the wrapper when a seal is present. No constructor changes; unconfigured output is byte-identical. See [research.md](research.md).

## Technical Context

**Language/Version**: PHP ^8.3, Laravel 12/13

**Primary Dependencies**: illuminate/view, illuminate/support (`asset()`), existing `Erecht24Settings`, `SealVariant`, `LegalTextType`

**Storage**: N/A (seal images are static assets under `vendor/erecht24/`; publishing is issue #2)

**Testing**: Pest + Orchestra Testbench (`tests/Feature/View/`), `Storage::fake()`, `Http::preventStrayRequests()`

**Target Platform**: Laravel package, Blade rendering

**Project Type**: library

**Performance Goals**: No extra I/O or HTTP at render; one config read per render

**Constraints**: Public constructors unchanged; unconfigured HTML byte-identical; published `legal-text` views unaware of `$seal` must still render; PHPStan level 8; Pint

**Scale/Scope**: 2 new classes, 1 modified component, 4 Blade views, ~10 tests

## Constitution Check

| Principle | Status |
|---|---|
| I. Test-first (Pest) | Pass: feature tests for every spec scenario incl. published-view override |
| II. Static analysis | Pass: typed DTO/resolver; `contentView()` array shape extended with `seal: ?LegalTextSeal` |
| III. Code style | Pass: Pint; `final`, `declare(strict_types=1)`, readonly DTO |
| IV. Backward-compatible API | Pass: additive (MINOR); constructors untouched; `seal` is an extra view variable; CHANGELOG "Unreleased" entry |
| V. Package conventions | Pass: registered in `ERecht24ServiceProvider::register()`, config read only via `Erecht24Settings` |

Post-design re-check: no violations; Complexity Tracking not needed.

## Project Structure

### Documentation (this feature)

```text
specs/013-seal-rendering/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/view-contract.md
└── tasks.md             # created by /speckit.tasks
```

### Source Code (repository root)

```text
src/
├── ERecht24ServiceProvider.php          # register SealResolver singleton
└── View/
    ├── LegalTextSeal.php                # new readonly DTO
    ├── SealResolver.php                 # new
    └── Components/AbstractLegalTextComponent.php  # pass 'seal'
resources/views/
├── components/legal-text.blade.php      # render seal block
├── imprint.blade.php                    # forward $seal
├── privacy-policy.blade.php             # forward $seal
└── privacy-policy-social-media.blade.php # forward $seal (always null)
tests/Feature/View/
├── SealRenderingTest.php                # new
└── PublishedViewsTest.php               # extend
tests/Unit/View/SealResolverTest.php     # new
CHANGELOG.md, CLAUDE.md                  # docs/status
docs/src/content/docs/{en,de}/...        # seal rendering note (EN/DE equivalent)
```

**Structure Decision**: Single-package layout; new classes live in `KaiHempel\ERecht24\View\` next to `LegalTextResolver` and `ResolvedLegalText`.
