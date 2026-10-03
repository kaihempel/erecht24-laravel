# Implementation Plan: Blade Views and Component with Placeholder

**Branch**: `006-blade-views-component` | **Date**: 2026-10-03 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/006-blade-views-component/spec.md`

## Summary

Add an `erecht24` Blade view namespace, a class-based `legal-text` component plus three convenience components, a shared wrapper view, three thin per-type views and a neutral `missing` fallback view. Components read only from the existing `LegalTextStore` (no HTTP). Language resolution (explicit → app locale reduced to primary subtag → first configured, then first available stored language) lives in a small internal resolver so it is unit-testable independent of Blade. Views are publishable under tag `erecht24-views`.

## Technical Context

**Language/Version**: PHP ^8.3

**Primary Dependencies**: Laravel 12/13 (`illuminate/view` — must be added to `composer.json` `require`; `illuminate/support` already present), existing `LegalTextStore`, `Erecht24Settings`, `LegalTextType`

**Storage**: Existing files on the configured disk (read-only here)

**Testing**: Pest + Orchestra Testbench; `Storage::fake()`, `Http::preventStrayRequests()`, `Blade::render()` / `$this->blade()`

**Target Platform**: Laravel applications (package)

**Project Type**: Laravel library

**Performance Goals**: At most one store read per render in the common case (resolved language hit); no caching

**Constraints**: No API calls on render; never throw on missing/unreadable text; PHPStan level 8; Pint

**Scale/Scope**: 4 components, 5 views, 1 resolver, ~10 test files/cases

## Constitution Check

| Principle | Status | Notes |
|---|---|---|
| I. Test-First with Pest | PASS | Resolver unit tests + component feature tests cover every fallback path (FR-013) |
| II. Static Analysis Gate | PASS | New classes typed, `final`, level 8 clean; `illuminate/view` added so Blade types resolve |
| III. Consistent Code Style | PASS | Pint run; formatting separate from behavior commits |
| IV. Backward-Compatible Public API | PASS | Purely additive (MINOR); CHANGELOG "Unreleased" entry. New public surface: component tags, `erecht24::` views, `erecht24-views` tag |
| V. Laravel Package Conventions | PASS | Wired through `ERecht24ServiceProvider` (`loadViewsFrom`, `Blade::componentNamespace`, `publishes`) |

Post-design re-check: PASS, no violations; Complexity Tracking not needed.

## Project Structure

### Documentation (this feature)

```text
specs/006-blade-views-component/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── blade-components.md
└── tasks.md             # created by /speckit-tasks
```

### Source Code (repository root)

```text
src/
├── ERecht24ServiceProvider.php          # + loadViewsFrom, component namespace, publishes
├── View/
│   ├── Components/
│   │   ├── LegalText.php                # generic component (type, lang)
│   │   ├── Imprint.php                  # convenience, extends LegalText
│   │   ├── PrivacyPolicy.php
│   │   └── PrivacyPolicySocialMedia.php
│   └── LegalTextResolver.php            # language resolution + store read (internal)
resources/views/
├── components/
│   └── legal-text.blade.php             # wrapper with {!! $content !!} placeholder
├── imprint.blade.php
├── privacy-policy.blade.php
├── privacy-policy-social-media.blade.php
└── missing.blade.php
tests/
├── Unit/View/LegalTextResolverTest.php
└── Feature/View/
    ├── LegalTextComponentTest.php       # per-type render, unescaped, no HTTP
    ├── LanguageFallbackTest.php         # explicit/locale/regional/unconfigured/missing file
    ├── MissingFallbackTest.php          # empty store, debug on/off, empty text, unreadable disk
    └── PublishedViewsTest.php           # erecht24-views publish + override
```

**Structure Decision**: Single library project. Rendering logic sits in a plain `LegalTextResolver` returning a result (content + resolved language, or null); components only call it and pick the view, keeping Blade thin.

## Complexity Tracking

No constitution violations.
