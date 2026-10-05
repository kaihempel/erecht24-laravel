---

description: "Task list for 007-manager-resolve-language (GitHub issue #27)"
---

# Tasks: Expose the Resolved Legal Text Language

**Input**: Design documents from `/specs/007-manager-resolve-language/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/public-api.md, quickstart.md

**Tests**: REQUIRED — spec FR-012 lists the test scenarios and Constitution Principle I (Test-First with Pest) is non-negotiable. Write each test task first and confirm it fails before the matching implementation task.

**Organization**: Tasks are grouped by user story so each story can be implemented and tested on its own.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: User story from spec.md (US1, US2, US3)
- Paths are relative to the repository root `/Users/erosol/Dev/Web/erecht24-laravel`

## Global rules for every task

- Every PHP file keeps `declare(strict_types=1);`, Pint style (`pint.json`), and full type declarations (PHPStan level 8, no `@phpstan-ignore`).
- **Do not modify or delete any existing test or assertion** (spec SC-003). Only add new `it()` / `test()` blocks.
- Existing test helpers to reuse: in `tests/Unit/View/LegalTextResolverTest.php` → `resolveLegalText()` and `storeResolverText(string $lang, string $html, LegalTextType $type = LegalTextType::Imprint)`; its `beforeEach` fakes storage, sets `erecht24.text_languages` to `de,en` and locale `de`. In `tests/Feature/ManagerTest.php` the `beforeEach` already calls `Http::preventStrayRequests()`, fakes storage, sets `de,en`, locale `de`, and stores **both** `de` and `en` for every `LegalTextType` (`<p>DE {type}</p>` / `<p>EN {type}</p>`); use `app(LegalTextStore::class)->forget($type, $lang)` to remove one.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm a green baseline before changing anything.

- [X] T001 Run `composer lint`, `composer analyse` and `composer test` on branch `007-manager-resolve-language` from the repository root and confirm all three pass before any change (record failures, if any, before proceeding).

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Carry the normalized requested language through the resolver result. Both US1 and US2 depend on it.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

### Tests (write first, must fail)

- [X] T002 [P] Create `tests/Unit/View/ResolvedLegalTextTest.php` (Pest, `declare(strict_types=1);`, `use KaiHempel\ERecht24\View\ResolvedLegalText;`) with: (a) `new ResolvedLegalText('<p>DE</p>', 'de')` still works and has `requestedLang === null` (backward-compatible two-argument construction, FR-008); (b) `new ResolvedLegalText('<p>DE</p>', 'de', 'en')` exposes `content`, `lang` and `requestedLang === 'en'`; (c) named-argument construction `new ResolvedLegalText(content: 'x', lang: 'de')` works.
- [X] T003 [P] Add new tests (do not change existing ones) to `tests/Unit/View/LegalTextResolverTest.php` asserting `requestedLang` on the result of `resolveLegalText()->resolve(LegalTextType::Imprint, …)`, with `de` and `en` both stored unless noted:
  - explicit `'en'` → `lang === 'en'`, `requestedLang === 'en'`;
  - explicit `null`, `app()->setLocale('en')` → `requestedLang === 'en'`;
  - explicit `null`, locale `'en-GB'` and `'en_GB'` (dataset) → `requestedLang === 'en'`, `lang === 'en'`;
  - explicit `' EN '` → `requestedLang === 'en'`;
  - explicit `'de'` while locale is `'en'` → `requestedLang === 'de'` (explicit wins over locale);
  - explicit `'fr'`, locale `'en'` → `lang === 'en'` and `requestedLang === 'fr'` (unconfigured request is still reported, research R2);
  - explicit `null`, `app()->setLocale('')` → `requestedLang === null`, `lang === 'de'` (first configured).

### Implementation

- [X] T004 Extend `src/View/ResolvedLegalText.php`: add the trailing promoted constructor property `public ?string $requestedLang = null,` after `public string $lang,` (class stays `final readonly`, namespace unchanged). Add a class docblock stating it is the public result of `Erecht24Manager::resolve()` and documenting the fields: `content` = stored HTML, `lang` = delivered language (configured code whose stored file was returned), `requestedLang` = "normalized requested language: explicit `$lang` if given and non-empty, otherwise the app locale; lowercase primary subtag. May be a code that is not configured (e.g. `fr`). `null` = nothing requested." Do not add an `@internal` tag. Do not add `isFallback()` yet (US2, T014). Makes T002 pass.
- [X] T005 Update `src/View/LegalTextResolver.php` `resolve()`: after the `try { $candidates = $this->candidates($lang); } catch …` block, compute once `$requested = $this->normalize($lang) ?? $this->normalize(app()->getLocale());` and construct `new ResolvedLegalText($content, $candidate, $requested)`. Leave `candidates()`, `normalize()`, `read()` and the class-level `@internal` tag unchanged (candidate order must not change, FR-004). Makes T003 pass.
- [X] T006 Run `composer test` and confirm T002/T003 pass and every pre-existing test is still green.

**Checkpoint**: Resolver results carry `requestedLang`; nothing public has changed yet.

---

## Phase 3: User Story 1 — Know which language was actually delivered (Priority: P1) 🎯 MVP

**Goal**: Application developers call `ERecht24::resolve($type, $lang)` and get content, delivered language and requested language in one call.

**Independent Test**: With `de` and `en` stored, `ERecht24::resolve('imprint', 'en')` returns a `ResolvedLegalText` with content `<p>EN imprint</p>`, `lang === 'en'`, `requestedLang === 'en'`; with nothing stored it returns `null`.

### Tests for User Story 1 (write first, must fail)

- [X] T007 [US1] Add new tests to `tests/Feature/ManagerTest.php` (import `KaiHempel\ERecht24\View\ResolvedLegalText`):
  - `ERecht24::resolve('imprint', 'en')` → instance of `ResolvedLegalText`, `content === '<p>EN imprint</p>'`, `lang === 'en'`, `requestedLang === 'en'`;
  - `app()->setLocale('en')`, `ERecht24::resolve('imprint')` → `lang === 'en'`;
  - `ERecht24::resolve('imprint', 'de')` → `lang === 'de'`;
  - enum and string are identical for every case: dataset `LegalTextType::cases()`, `ERecht24::resolve($type, 'en')` `toEqual` `ERecht24::resolve($type->value, 'en')`;
  - consistency with existing methods (contract invariant): for `'en'`, `'de'` and `null`, `ERecht24::resolve('imprint', $lang)?->content === ERecht24::html('imprint', $lang)` and `(ERecht24::resolve('imprint', $lang) !== null) === ERecht24::has('imprint', $lang)`;
  - after `forget(LegalTextType::Imprint, 'de')` and `forget(LegalTextType::Imprint, 'en')`, `ERecht24::resolve('imprint')` is `null`;
  - invalid language configuration: `config(['erecht24.text_languages' => 'fr'])` (unsupported code, `Erecht24Settings::languages()` throws `InvalidConfigurationException`) → `ERecht24::resolve('imprint')` returns `null` and does not throw (spec edge case "Invalid language configuration"); optionally `Log::spy()` and assert a warning was logged;
  - `ERecht24::resolve('impressum')` throws `\InvalidArgumentException` with message `Unknown legal text type [impressum].`;
  - also call `app(Erecht24Manager::class)->resolve(LegalTextType::Imprint, 'en')` directly and assert `lang === 'en'`.
  No outgoing HTTP is guaranteed by the existing `Http::preventStrayRequests()` in `beforeEach` (SC-004) — do not remove it.

### Implementation for User Story 1

- [X] T008 [US1] Add to `src/Erecht24Manager.php` (between `has()` and `lastModified()`), importing `KaiHempel\ERecht24\View\ResolvedLegalText`:
  ```php
  /**
   * The text that `html()` would return together with the delivered and requested language,
   * or null if none is stored.
   *
   * @throws \InvalidArgumentException when $type is not a known legal text type
   */
  public function resolve(LegalTextType|string $type, ?string $lang = null): ?ResolvedLegalText
  {
      return $this->resolver->resolve($this->type($type), $lang);
  }
  ```
  Do not change `html()`, `has()`, `lastModified()`, `languages()` or `type()`.
- [X] T009 [P] [US1] In `src/Facades/ERecht24.php` add `use KaiHempel\ERecht24\View\ResolvedLegalText;` and the docblock line `@method static ResolvedLegalText|null resolve(LegalTextType|string $type, ?string $lang = null)` directly after the `has` line.
- [X] T010 [US1] Run `composer test` and `composer analyse`; T007 passes, all existing tests stay green.

**Checkpoint**: MVP — `ERecht24::resolve()` exposes the delivered language.

---

## Phase 4: User Story 2 — Detect a language fallback (Priority: P1)

**Goal**: `ResolvedLegalText::isFallback()` tells whether the delivered language differs from the requested one.

**Independent Test**: With only `de` stored and locale `en`, `LegalTextResolver::resolve(LegalTextType::Imprint, null)` reports `lang === 'de'`, `requestedLang === 'en'`, `isFallback() === true` (T011/T012, no US1 needed). The facade-level variant via `ERecht24::resolve('imprint')` (T013) additionally requires US1 (T008/T009) — see spec US2 "Dependency".

### Tests for User Story 2 (write first, must fail)

- [X] T011 [P] [US2] Add an `isFallback()` truth-table test to `tests/Unit/View/ResolvedLegalTextTest.php` (dataset): `('de', null)` → false; `('de', 'de')` → false; `('de', 'en')` → true; `('en', 'fr')` → true (arguments are `lang`, `requestedLang`). Rule under test: "`requestedLang !== null && requestedLang !== lang`".
- [X] T012 [P] [US2] Add new tests to `tests/Unit/View/LegalTextResolverTest.php` matching the derivation table in `data-model.md`:
  - only `de` stored, locale `en`, explicit `null` → `lang 'de'`, `requestedLang 'en'`, `isFallback()` true;
  - only `de` stored, locale `de`, explicit `'en'` → `isFallback()` true;
  - `de`+`en` stored, locale `en-GB` → `isFallback()` false;
  - `de`+`en` stored, explicit `'DE'`, locale `en` → `lang 'de'`, `isFallback()` false;
  - `de`+`en` stored, explicit `'fr'`, locale `en` → `lang 'en'`, `isFallback()` true;
  - `de`+`en` stored, locale `''`, explicit `null` → `requestedLang null`, `isFallback()` false.
- [X] T013 [US2] Add new tests to `tests/Feature/ManagerTest.php`:
  - locale `en` with both stored → `ERecht24::resolve('imprint')->isFallback()` false;
  - `forget(LegalTextType::Imprint, 'en')`, locale `en` → `lang 'de'`, `requestedLang 'en'`, `isFallback()` true, `content === '<p>DE imprint</p>'`;
  - `forget(LegalTextType::Imprint, 'en')`, explicit `ERecht24::resolve('imprint', 'en')` → `isFallback()` true;
  - `app()->setLocale('en-GB')` with both stored → `requestedLang 'en'`, `isFallback()` false.

### Implementation for User Story 2

- [X] T014 [US2] Add to `src/View/ResolvedLegalText.php`:
  ```php
  /**
   * True when a language was requested and a different one was delivered.
   */
  public function isFallback(): bool
  {
      return $this->requestedLang !== null && $this->requestedLang !== $this->lang;
  }
  ```
- [X] T015 [US2] Run `composer test` and `composer analyse`; T011–T013 pass, all existing tests stay green.

**Checkpoint**: US1 and US2 both work; consumers can show an "only available in German" notice.

---

## Phase 5: User Story 3 — Documentation and release notes (Priority: P2)

**Goal**: Developers discover `resolve()` in both READMEs and the changelog.

**Independent Test**: The "Facade and Inertia" / "Facade und Inertia" sections list `resolve()` with a short example, and `CHANGELOG.md` has the entry under "Unreleased / Added".

- [X] T016 [P] [US3] In `README.md`, section `### Facade and Inertia`: add a table row after the `html` row — `` `ERecht24::resolve(LegalTextType\|string $type, ?string $lang = null)` `` | `` `?ResolvedLegalText` – `content`, delivered `lang`, normalized `requestedLang` and `isFallback()`, or `null` if none is stored `` — and, after the existing Inertia controller example, a short paragraph plus code example showing `$text = ERecht24::resolve(LegalTextType::Imprint);` passing `'html' => $text?->content`, `'lang' => $text?->lang` and `'isFallback' => $text?->isFallback() ?? false` to `Inertia::render(...)`, explaining that `lang` belongs on the wrapper's `lang` attribute and `isFallback` drives an "only available in German" notice. Mention that `ResolvedLegalText` is `KaiHempel\ERecht24\View\ResolvedLegalText`.
- [X] T017 [P] [US3] In `README.de.md`, section `### Facade und Inertia`: add the content-equivalent German table row and example (same code, German prose, e.g. „nur auf Deutsch verfügbar“-Hinweis). Keep both READMEs content-equivalent (CLAUDE.md requirement).
- [X] T018 [P] [US3] In `CHANGELOG.md` under `## Unreleased` → `### Added`, add as first bullet: `` `Erecht24Manager::resolve()` / `ERecht24::resolve()` returning `?ResolvedLegalText` with the stored `content`, the delivered `lang`, the normalized `requestedLang` (explicit language, otherwise app locale) and `isFallback()`, so applications can set the `lang` attribute and show a fallback notice. `KaiHempel\ERecht24\View\ResolvedLegalText` is now public API and gains the optional third constructor argument `requestedLang` (backward compatible, MINOR). Never calls the API; unknown types throw `InvalidArgumentException`. (#27) ``

**Checkpoint**: All user stories complete and documented.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T019 Run `composer lint` (use `composer format` if needed; keep any formatting-only fixes in a separate commit per Constitution III).
- [X] T020 Run `composer analyse` (PHPStan level 8, `src/`) — zero errors, no new suppressions.
- [X] T021 Run `composer test` — full suite green, including `tests/Unit/ReadmeEnvReferenceTest.php`; run `git diff main -- tests/` and confirm only additions (no changed or removed existing assertions, SC-003).
- [X] T022 Walk through `specs/007-manager-resolve-language/quickstart.md` and confirm the documented behavior (only `de` stored, locale `en` → `lang 'de'`, `requestedLang 'en'`, `isFallback()` true) matches the implementation.
- [X] T023 [P] Update `CLAUDE.md`: in the `Erecht24Manager` architecture bullet add `resolve()` (returns public `ResolvedLegalText` with `lang`, `requestedLang`, `isFallback()`), and in `## Status` add `007 manager resolve() / resolved language (#27)`.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: none.
- **Foundational (Phase 2)**: after T001; blocks all user stories.
- **US1 (Phase 3)**: after Phase 2.
- **US2 (Phase 4)**: after Phase 2. Intentional, documented dependency (spec US2 "Dependency"): its facade-level tests (T013) call `ERecht24::resolve()`, so T013 needs T008/T009 from US1; T011, T012 and T014 only need Phase 2 and are US2's independent test.
- **US3 (Phase 5)**: documents US1 + US2; write after both are implemented (can be drafted in parallel).
- **Polish (Phase 6)**: after all stories.

### Within Each Phase

- Test tasks before implementation tasks; confirm the tests fail first.
- T004 before T005 (resolver constructs the extended object).
- T014 edits the same file as T004 — run sequentially.
- T007 and T013 both edit `tests/Feature/ManagerTest.php`; T003 and T012 both edit `tests/Unit/View/LegalTextResolverTest.php`; T002 and T011 both edit `tests/Unit/View/ResolvedLegalTextTest.php` — never run these pairs in parallel.

### Parallel Opportunities

- T002 ∥ T003 (different test files).
- T009 ∥ T008 (facade vs. manager).
- T011 ∥ T012 (different test files).
- T016 ∥ T017 ∥ T018 (different documentation files).
- T023 ∥ T019–T022.

---

## Parallel Example: Phase 2

```bash
Task: "Create tests/Unit/View/ResolvedLegalTextTest.php (T002)"
Task: "Add requestedLang tests to tests/Unit/View/LegalTextResolverTest.php (T003)"
```

## Parallel Example: User Story 3

```bash
Task: "Document resolve() in README.md (T016)"
Task: "Document resolve() in README.de.md (T017)"
Task: "Add CHANGELOG.md entry (T018)"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. T001 baseline → Phase 2 (T002–T006).
2. Phase 3 (T007–T010) → `ERecht24::resolve()` returns the delivered language.
3. **Stop and validate**: run the US1 independent test.

### Incremental Delivery

1. Foundation → US1 (delivered language) → US2 (`isFallback()`) → US3 (docs) → Polish.
2. Each step leaves `html()`, `has()`, `lastModified()` and `languages()` unchanged and the suite green.
3. One branch/PR for the whole spec (project convention), commit per phase.

---

## Notes

- [P] = different files, no dependencies on incomplete tasks.
- Never put API keys/secrets in messages; this feature touches no HTTP code.
- `LegalTextResolver` stays `@internal`; only `ResolvedLegalText` becomes public API.
