# Feature Specification: German Translation of All Documentation Pages

**Feature Branch**: `010-german-docs-translation`

**Created**: 2026-10-14

**Status**: Draft (clarified)

**Input**: User description: "Add the German translation of all documentation pages (GitHub issue #36). The Starlight docs site under docs/src/content/docs/de/ must mirror every English page from docs/src/content/docs/en/ one-to-one (same file names, same heading order, same components such as Steps/Tabs/asides). Content source is README.de.md. Depends on spec 009."

## Clarifications

### Session 2026-10-14

- Q: How are German counterparts of English placeholder pages handled? → A: They mirror the English state: `project/changelog`, `reference/exceptions` and `project/troubleshooting` keep a German "Demnächst verfügbar" placeholder until the English pages get real content.
- Q: Which form of address is used? → A: Formal "Sie", matching `README.de.md` (no informal "du" there).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Read the complete documentation in German (Priority: P1)

A German-speaking developer opens the documentation site in German and finds every page (getting started, guides, usage, reference, project) fully translated wherever the English page has content, instead of the current "coming soon" placeholders.

**Why this priority**: This is the core value. Without real German content the German site is unusable.

**Independent Test**: Open each German page and confirm it contains full German content equivalent to its English counterpart, with no placeholder text.

**Acceptance Scenarios**:

1. **Given** an English page exists, **When** the reader opens the same page in German, **Then** it shows complete German content with a translated title and description.
2. **Given** any German page, **When** it is opened, **Then** no "not translated" notice is shown.
3. **Given** a German page with code blocks, commands, class names or environment variable names, **When** it is read, **Then** those remain untranslated and identical to the English page.

---

### User Story 2 - Navigate within the German site (Priority: P1)

A German reader follows links between pages and stays in the German version of the site; the language switcher moves them to the matching English page and back.

**Why this priority**: Broken or English-pointing links undermine the translation and the language switcher depends on matching page paths.

**Independent Test**: Follow every internal link on every German page; each lands on an existing German page. Use the language switcher on each page and confirm it reaches the counterpart.

**Acceptance Scenarios**:

1. **Given** a German page with an internal link, **When** the link is followed, **Then** it opens an existing page under the German path prefix.
2. **Given** any German page, **When** the reader switches language, **Then** the English counterpart with the same path opens.

---

### User Story 3 - Look up configuration variables in German (Priority: P2)

A German-speaking developer configuring the package consults the German configuration reference and finds every environment variable the package supports, each written in code formatting.

**Why this priority**: Configuration is the most frequently looked-up reference, and completeness is required to keep both languages equivalent.

**Independent Test**: Compare the set of environment variables in the German configuration reference to the set in the English one; they must be identical.

**Acceptance Scenarios**:

1. **Given** the German configuration page, **When** its environment variables are listed, **Then** every variable documented in English appears, each in backticks.

---

### User Story 4 - Maintainers keep both languages in sync (Priority: P2)

A maintainer relies on the automated docs checks and CI build to fail when German and English diverge, and to pass when the translation is complete.

**Why this priority**: Prevents future drift and confirms the feature is releasable.

**Independent Test**: Run the docs build including its parity and content checks; it succeeds.

**Acceptance Scenarios**:

1. **Given** the completed translation, **When** the docs build runs locally and in CI, **Then** it succeeds.
2. **Given** a German page is missing or renamed, **When** the checks run, **Then** they report the mismatch.

### Edge Cases

- A page whose English version uses special components (step lists, tabs, callout asides): the German page uses the same components at the same positions and in the same heading order.
- English pages with a different file extension than the German counterpart: counterparts are matched by name regardless of extension.
- English content added after the German source text was written: the German page must still cover all English sections; any gap is filled by translating the English page.
- Links to anchors on other pages: anchors must resolve against the German headings.
- Terms with no established German equivalent: kept in English (e.g., product, class and command names).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Every English documentation page MUST have a German counterpart with the same file name and location.
- **FR-002**: Each German page whose English counterpart has real content MUST contain full German content equivalent to it, using `README.de.md` as primary source, with no placeholder text remaining. German counterparts of English placeholder pages (`project/changelog`, `reference/exceptions`, `project/troubleshooting`) MUST keep a German placeholder ("Demnächst verfügbar") until the English pages gain content.
- **FR-003**: The title and description of each German page MUST be translated.
- **FR-004**: Each German page MUST keep the same heading order and use the same components (step lists, tabs, asides) at the same positions as the English page.
- **FR-005**: All internal links on German pages MUST point to German-language paths and MUST resolve to existing pages.
- **FR-006**: Code blocks, command names, class names and environment variable names MUST remain untranslated.
- **FR-007**: No German page MAY display the site's "not translated" notice.
- **FR-008**: The German configuration reference MUST list every `ERECHT24_*` environment variable, each in backticks, matching the English page.
- **FR-009**: The language switcher MUST map each German page to its English counterpart and vice versa.
- **FR-010**: The docs build, including the English/German parity check and the content check, MUST succeed locally and in CI.
- **FR-011**: This feature MUST NOT change any English page, `README.md` or `README.de.md`; German docs and `README.de.md` remain content-equivalent as an ongoing maintenance rule.

### Key Entities

- **Documentation page**: A unit of content identified by its path (section and name), with title, description, headings and body, existing in English and German.
- **Page pair**: An English page and its German counterpart, matched by path; must be content-equivalent.
- **Environment variable reference**: The set of `ERECHT24_*` variables documented on the configuration page; identical in both languages.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of English pages (19 of 19) have a German counterpart, and 100% of those with real English content (16 of 19) have complete German content with no placeholder text.
- **SC-002**: 0 internal links on German pages point to English paths or to missing pages.
- **SC-003**: 0 German pages show a "not translated" notice.
- **SC-004**: 100% of `ERECHT24_*` variables from the English configuration page appear in backticks on the German one.
- **SC-005**: The docs build and its automated checks pass on the first CI run after the change.
- **SC-006**: A German reader can reach any page from the German navigation and switch to its English counterpart in one step.

## Assumptions

- The English pages from spec 009 are the structural source of truth; `README.de.md` supplies the German text, and sections absent from it are translated from the English page.
- Formal German address ("Sie") is used throughout, matching `README.de.md`.
- Pages that were not part of the English README content (e.g., index) are translated from their English page.
- Writing real content for the three English placeholder pages is out of scope.
- Existing automated checks already cover file-name parity and the English content contract; no new tooling is required, though a German link/variable check may be added if the existing checks do not cover it.
- Deployment/hosting of the docs site is out of scope.
- Depends on spec 009 (English README moved into Starlight pages) being merged.
