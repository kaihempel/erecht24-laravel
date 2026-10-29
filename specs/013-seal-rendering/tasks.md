---

description: "Task list for rendering the trust seal below imprint and privacy policy"
---

# Tasks: Render the trust seal below imprint and privacy policy

**Input**: Design documents from `/specs/013-seal-rendering/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/view-contract.md

**Tests**: Required (spec FR-013, constitution principle I). Pest + Testbench; use `Storage::fake()`, `Http::preventStrayRequests()` and `config()` as in `tests/Feature/View/LegalTextComponentTest.php`.

## Format: `- [ ] [ID] [P?] [Story] Description with file path`

## Phase 1: Setup

- [X] T001 Add a byte-identical regression test in `tests/Feature/View/SealRenderingTest.php` that renders imprint, privacy policy, social-media and generic `legal-text` WITHOUT seal config and asserts the exact pre-change markup (`<div lang="de">` + content + newline + `</div>`, as produced by the current `resources/views/components/legal-text.blade.php`); run `composer test` to confirm it passes before any code change

## Phase 2: Foundational (blocks all stories)

- [X] T002 [P] Create `final readonly class LegalTextSeal` with `string $url, string $alt, int $width, int $height, int $displayWidth` in `src/View/LegalTextSeal.php` (`declare(strict_types=1)`)
- [X] T003 Create `final class SealResolver` in `src/View/SealResolver.php`: ctor `Erecht24Settings`; `for(LegalTextType $type, string $lang): ?LegalTextSeal`. Imprint → `imprintSeal()` + `impressum-siegel-{fileSuffix()}.webp`; PrivacyPolicy → `privacyPolicySeal()` + `datenschutz-siegel-{fileSuffix()}.webp`; PrivacyPolicySocialMedia → `null`; null setting → `null`; `url` = `asset('vendor/erecht24/'.$file)`; width/height/displayWidth from `SealVariant`; alt per type: de "Impressum – rechtssicher mit eRecht24" / en "Legal notice – legally compliant with eRecht24", de "Datenschutzerklärung – rechtssicher mit eRecht24" / en "Privacy policy – legally compliant with eRecht24"; any other lang → English (depends on T002)
- [X] T004 Register `SealResolver` singleton in `src/ERecht24ServiceProvider.php` `register()` (injecting `Erecht24Settings`) (depends on T003)
- [X] T005 [P] Unit tests in `tests/Unit/View/SealResolverTest.php`: all 4 variants × imprint/privacy policy (url, 1425×600→300 / 900×1400→180), social-media always null, unconfigured null, de/en alt texts, invalid config throws `InvalidConfigurationException`; add singleton assertion in `tests/Unit/ServiceProviderTest.php` (depends on T003, T004)

**Checkpoint**: Resolver works in isolation.

## Phase 3: User Story 1 - Seal below imprint and privacy policy (P1) 🎯 MVP

**Goal**: Configured seal renders centered below text for imprint, privacy policy and generic tag.

**Independent Test**: Configure `seals.imprint=light-horizontal`, store imprint text, render `<x-erecht24::imprint />`; image follows text inside wrapper.

- [X] T006 [P] [US1] Feature tests in `tests/Feature/View/SealRenderingTest.php`: imprint `light-horizontal` renders `<img>` after the text, inside the wrapper, with `src` ending `vendor/erecht24/impressum-siegel-light-horizontal-large.webp`, `width="1425" height="600"`, 300px style, `max-width: 100%`, centered `erecht24-seal` container (`text-align: center`), `loading="lazy"` and `height: auto`; the URL is emitted regardless of whether the image file exists; privacy policy `dark-vertical` renders `datenschutz-siegel-dark-vertical-large.webp`, `width="900" height="1400"`, 180px; generic `<x-erecht24::legal-text type="imprint">` and `type="privacyPolicy"` render the seal (write first, expect failures)
- [X] T007 [US1] In `src/View/Components/AbstractLegalTextComponent.php` `render()`: resolve `app(SealResolver::class)->for($type, $resolved->lang)` and add `'seal' => $seal` to the data array; update the `@param array{content: string, type: LegalTextType, lang: string, seal: ?LegalTextSeal}` of `contentView()`; do NOT change constructors; `erecht24::missing` call stays unchanged (depends on T004)
- [X] T008 [US1] Extend `resources/views/components/legal-text.blade.php` per `contracts/view-contract.md`: after `{!! $content !!}` add `@if (! empty($seal))` block with centered `div.erecht24-seal` and `<img>` (src, alt, width, height, `loading="lazy"`, style `width: {displayWidth}px; max-width: 100%; height: auto;`); add `$seal` to the header comment variable list; place the `@if`/`@endif` directives inline (on the same line as `{!! $content !!}` or via `@if(...)…@endif` without added blank lines) so the unconfigured output stays byte-identical and T001 keeps passing (depends on T007)
- [X] T009 [P] [US1] Forward `'seal' => $seal ?? null` to the `@include('erecht24::components.legal-text', …)` in `resources/views/imprint.blade.php`, `resources/views/privacy-policy.blade.php` and `resources/views/privacy-policy-social-media.blade.php`. No PHP change in `Imprint`, `PrivacyPolicy`, `PrivacyPolicySocialMedia` or `LegalText`: their `contentView()` already passes `$data` (incl. `seal`) to `View::first` (depends on T007)

**Checkpoint**: US1 tests (T006) pass.

## Phase 4: User Story 2 - No seal where none is wanted (P1)

**Goal**: No seal for social-media, missing text, or unconfigured sites; unconfigured output unchanged.

**Independent Test**: Render each case without/with irrelevant config; no `<img class` seal.

- [X] T010 [P] [US2] Add tests to `tests/Feature/View/SealRenderingTest.php`: social-media never renders a seal with both seals configured; no seal when nothing configured (T001 already pins the exact markup); no seal when text missing (`erecht24::missing`) with seal configured; invalid config value (`seals.imprint=blue`) surfaces as `InvalidConfigurationException` wrapped in `ViewException`; rendering performs no HTTP request (`Http::preventStrayRequests()` + `Http::assertNothingSent()`)
- [X] T011 [US2] Make all T010 tests pass; adjust `resources/views/components/legal-text.blade.php` only if T001 or T010 reveal whitespace or seal leakage (depends on T008, T009)

## Phase 5: User Story 3 - Description matches language (P2)

**Goal**: Alt text German for `de`, English for `en`.

- [X] T012 [US3] Add tests to `tests/Feature/View/SealRenderingTest.php`: imprint and privacy policy with `lang="de"` and `lang="en"` produce the four exact alt strings (incl. en-dash `–`); fallback language renders the alt of the rendered language (depends on T008)

## Phase 6: User Story 4 - Existing integrations keep working (P2)

**Goal**: Published overrides and constructors unaffected.

- [X] T013 [P] [US4] Extend `tests/Feature/View/PublishedViewsTest.php`: published `components/legal-text` override that ignores `$seal` renders without error while a seal is configured
- [X] T014 [P] [US4] Add reflection test in `tests/Feature/View/SealRenderingTest.php` (or `tests/Unit/View/`) asserting constructor parameter lists of `Imprint`, `PrivacyPolicy`, `PrivacyPolicySocialMedia`, `LegalText` are unchanged (resolver, lang, plus existing LegalText params)

## Phase 7: Polish & Cross-Cutting

- [X] T015 [P] Add "Unreleased" entry to `CHANGELOG.md` (seal rendering, additive/MINOR)
- [X] T016 [P] Document seal rendering in EN and DE docs pages (same file names, content-equivalent; follow `docs/` contract, no H1) and update `CLAUDE.md` Architecture (`View/` seal) and Status (013)
- [X] T017 Run `composer test`, `composer lint` (`composer format` if needed) and `composer analyse`; run `cd docs && npm run check` if docs changed; fix all findings

## Dependencies & Execution Order

- Phase 1 → Phase 2 (T002 → T003 → T004 → T005) → US1 (T007 → T008/T009; T006 written first) → US2 (T010, T011) → US3 (T012) → US4 (T013, T014 independent after T008) → Polish.
- US3 and US4 depend only on US1 and can run in parallel with US2.

## Parallel Examples

- T002 ‖ (tests T005 once T003 done); T006 ‖ T007 prep; T009 ‖ T008 (different files); T013 ‖ T014 ‖ T015 ‖ T016.

## Implementation Strategy

- MVP = Phases 1–3 (resolver + imprint/privacy policy rendering). Then US2 (safety), US3, US4, polish. Commit per phase; all quality gates before PR.
