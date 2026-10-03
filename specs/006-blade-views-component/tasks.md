---

description: "Task list for Blade Views and Component with Placeholder"
---

# Tasks: Blade Views and Component with Placeholder

**Input**: Design documents from `/specs/006-blade-views-component/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/blade-components.md, quickstart.md

**Tests**: Required (spec FR-013, constitution Principle I). Pest + Orchestra Testbench; tests written before implementation within each story.

**Organization**: Grouped by user story. US1–US3 are all P1 and share the resolver, so the resolver is foundational.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1 = show stored text, US2 = language choice, US3 = fallback, US4 = publish/customize

## Phase 1: Setup

- [ ] T001 Add `"illuminate/view": "^12.0|^13.0"` to `require` in `composer.json` and run `composer update illuminate/view`
- [ ] T002 [P] Create empty directories/files scaffolding: `src/View/Components/`, `tests/Unit/View/`, `tests/Feature/View/`; remove `resources/views/.gitkeep` once the first view exists

## Phase 2: Foundational (blocks all stories)

- [ ] T003 [P] Create readonly value object `ResolvedLegalText` (`string $content`, `string $lang`) in `src/View/ResolvedLegalText.php` (`declare(strict_types=1)`, `final`)
- [ ] T004 Write failing unit tests for `LegalTextResolver` in `tests/Unit/View/LegalTextResolverTest.php` using `Storage::fake()`: explicit lang wins; app locale used when no lang; regional locale `de_DE`/`de-AT` → `de`; first configured language when locale unconfigured; fallback to a stored language follows configured-language order (e.g. languages `en,de` with an unconfigured requested language and both stored → `en` wins); unconfigured explicit lang falls back to first available stored language; missing file for resolved lang falls back to other stored language; blank/whitespace content treated as missing; nothing stored → `null`; store read throwing `LegalTextStoreException` → treated as missing (no exception)
- [ ] T005 Implement `LegalTextResolver::resolve(LegalTextType $type, ?string $lang): ?ResolvedLegalText` in `src/View/LegalTextResolver.php` per data-model.md rules (normalize to lowercase primary subtag, keep only `Erecht24Settings::languages()`, dedupe candidates `[lang, app()->getLocale(), first configured]`, then iterate configured languages; catch `\Throwable` from store reads per language). Must make T004 pass
- [ ] T006 Register `LegalTextResolver` singleton in `src/ERecht24ServiceProvider.php::register()`
- [ ] T007 Add provider `boot()` wiring in `src/ERecht24ServiceProvider.php`: `loadViewsFrom(__DIR__.'/../resources/views', 'erecht24')` and `Blade::componentNamespace('KaiHempel\\ERecht24\\View\\Components', 'erecht24')`

**Checkpoint**: resolver tested; namespace registered.

## Phase 3: User Story 1 - Show a stored legal text (P1) 🎯 MVP

**Goal**: Each tag renders stored HTML unescaped inside the wrapper, no HTTP.

**Independent Test**: Store HTML for all three types, render each tag and `<x-erecht24::legal-text type=…>`; assert raw markup present.

- [ ] T008 [P] [US1] Write failing feature tests in `tests/Feature/View/LegalTextComponentTest.php`: each convenience tag renders its own type's text with markup intact (`<h1>`/`<script>` not escaped); generic tag with `type="imprint"`, `privacyPolicy`, `privacy-policy`, `privacy-policy-social-media` slug forms equals matching convenience tag; invalid `type` throws `\InvalidArgumentException` naming allowed values; extra attributes (e.g. `class="prose"`) reach the wrapper (FR-014); `Http::preventStrayRequests()` active in every test
- [ ] T009 [P] [US1] Create wrapper view `resources/views/components/legal-text.blade.php`: container element merging `$attributes`, single placeholder `{!! $content !!}`, Blade comment documenting that content is trusted eRecht24 API HTML and not sanitized
- [ ] T010 [P] [US1] Create thin views `resources/views/imprint.blade.php`, `resources/views/privacy-policy.blade.php`, `resources/views/privacy-policy-social-media.blade.php`, each `@include('erecht24::components.legal-text', …)` passing `$content`, `$type`, `$lang`, `$attributes`
- [ ] T011 [US1] Implement `src/View/Components/LegalText.php` (`type` accepts `LegalTextType` wire values and file slugs else `\InvalidArgumentException` listing allowed values; optional `lang`; `render()` resolves via `LegalTextResolver` and returns wrapper view, or `erecht24::missing` when `null`)
- [ ] T012 [P] [US1] Implement `src/View/Components/Imprint.php`, `PrivacyPolicy.php`, `PrivacyPolicySocialMedia.php` extending `LegalText`; each only supplies its fixed `LegalTextType` and its thin view name (`erecht24::imprint`, `erecht24::privacy-policy`, `erecht24::privacy-policy-social-media`). Resolution and the `erecht24::missing` fallback stay in the shared `LegalText::render()` so all subclasses inherit them (no per-subclass fallback logic)

**Checkpoint**: US1 tests (T008) pass; MVP deliverable.

## Phase 4: User Story 2 - Choose the language (P1)

**Goal**: `lang` attribute, app locale, regional locale and fallbacks behave per FR-005/FR-006 through the real components.

**Independent Test**: Store `de` and `en` texts; vary attribute/locale/config.

- [ ] T013 [US2] Write feature tests in `tests/Feature/View/LanguageFallbackTest.php`: `lang="en"` overrides `app()->setLocale('de')`; no attribute uses app locale; locale `de_DE` shows `de` text; locale `fr` with languages `de,en` shows first configured; `lang="fr"` (unconfigured) shows first available stored language; resolved language file missing but other language stored → other text shown, not fallback view; applies to all three convenience tags; run until green, adjusting `src/View/LegalTextResolver.php` / `src/View/Components/LegalText.php` if end-to-end wiring (including `lang` propagation to thin views) exposes gaps

## Phase 5: User Story 3 - Fallback when no text exists (P1)

**Goal**: Neutral fallback view, hint only in debug, never blank/exception.

**Independent Test**: Empty store with `app.debug` true then false.

- [ ] T014 [P] [US3] Write feature tests in `tests/Feature/View/MissingFallbackTest.php`: empty store renders non-empty fallback for each tag; `config(['app.debug' => true])` output contains `php artisan erecht24:sync`; `app.debug` false output does not contain it; stored empty/whitespace file → fallback; unreadable disk (disk driver throwing) → fallback, no exception; no HTTP request (`Http::preventStrayRequests()`)
- [ ] T015 [P] [US3] Create `resources/views/missing.blade.php`: neutral message (English) plus `@if (config('app.debug'))` block with the `php artisan erecht24:sync` hint; uses `$type` for context
- [ ] T016 [US3] Ensure `LegalText::render()` returns `erecht24::missing` with `type` for the `null` resolver result (verify T014 passes; adjust `src/View/Components/LegalText.php` if needed)

## Phase 6: User Story 4 - Publish and customize (P2)

**Goal**: `erecht24-views` tag publishes views; published copies win.

**Independent Test**: Publish, edit wrapper copy, render.

- [ ] T017 [P] [US4] Write feature tests in `tests/Feature/View/PublishedViewsTest.php`: `ServiceProvider::pathsToPublish(ERecht24ServiceProvider::class, 'erecht24-views')` maps `resources/views` → `resource_path('views/vendor/erecht24')`; after writing a custom `resources/views/vendor/erecht24/components/legal-text.blade.php` into the app view path, rendering a tag uses the custom markup for all three types; editing only the wrapper affects all types
- [ ] T018 [US4] Add `publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/erecht24')], 'erecht24-views')` to `boot()` in `src/ERecht24ServiceProvider.php`

## Phase 7: Polish & Cross-Cutting

- [ ] T019 [P] Extend `tests/Unit/ServiceProviderTest.php` to assert the `erecht24` view namespace is registered and the resolver is a singleton
- [ ] T020 [P] Add "Unreleased → Added" entry to `CHANGELOG.md` (MINOR: Blade components, views, `erecht24-views` tag, `illuminate/view` requirement)
- [ ] T021 [P] Update `CLAUDE.md` Architecture/Status and `README.md` usage section with component tags, language resolution, publishing, and the unescaped-HTML trust note (content from quickstart.md)
- [ ] T022 Run `composer format`, then `composer lint`, `composer analyse`, `composer test`; fix any failures; confirm `Http::preventStrayRequests()` assertions pass in all view tests

## Dependencies & Execution Order

- Phase 1 → Phase 2 → US1 → (US2, US3, US4 in parallel after US1 component classes exist) → Polish
- T004 before T005; T005 before T006/T011; T007 before any rendering test
- US1: T008, T009, T010 parallel; T011 after T009/T010; T012 after T011
- US2 depends on US1 (T011/T012); US3 depends on T011 (T015 independent file); US4 depends only on T007 + views from US1
- Phase 7 after all stories

## Parallel Examples

- After T007: T008 ∥ T009 ∥ T010
- After US1: T013 ∥ T014 ∥ T015 ∥ T017
- Polish: T019 ∥ T020 ∥ T021

## Implementation Strategy

- **MVP**: Phases 1–3 (resolver + US1). Delivers rendering of stored text; the fallback (US3) should ship in the same PR since a blank imprint is a compliance risk.
- Then US2 → US3 → US4, committing per story. One branch/PR for the spec (`006-blade-views-component`).
