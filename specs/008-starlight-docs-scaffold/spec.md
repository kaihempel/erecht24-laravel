# Feature Specification: Starlight Documentation Site Scaffold

**Feature Branch**: `008-starlight-docs-scaffold`

**Created**: 2026-10-13

**Status**: Draft

**Input**: User description: "Scaffold the Starlight documentation site in `docs/` (GitHub issue #34). The README (~410 lines per language) is too long to navigate. Documentation moves into a bilingual (EN/DE) documentation site living next to the code in `docs/`, so code and docs are versioned and reviewed together. This feature only creates the skeleton (configuration, navigation, placeholder pages, CI check); content follows in later issues."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Browse a bilingual documentation skeleton (Priority: P1)

A package user or contributor opens the documentation site and sees a landing page and a navigation with five groups (Getting started, Guides, Usage, Reference, Project) in either English or German, and can switch language at any time.

**Why this priority**: The navigable structure in both languages is the core deliverable; later content issues depend on it.

**Independent Test**: Build and open the site locally; visit the English and German entry pages, check the sidebar groups and the language switcher.

**Acceptance Scenarios**:

1. **Given** the built site, **When** a visitor opens the English entry page, **Then** the five sidebar groups are shown with English labels and a language switcher offering English and Deutsch.
2. **Given** the built site, **When** a visitor opens the German entry page, **Then** the same five groups are shown with German labels (Erste Schritte, Anleitungen, Verwendung, Referenz, Projekt).
3. **Given** any page in one language, **When** the visitor switches language, **Then** they land on the equivalent page in the other language.
4. **Given** any planned page, **When** a visitor opens it, **Then** it shows a title, a description and a "Coming soon" body.

---

### User Story 2 - Docs build is verified automatically (Priority: P2)

A maintainer opens a pull request that touches the documentation or the changelog and gets an automatic build check, so a broken docs site is caught before merge. No deployment happens yet.

**Why this priority**: Prevents the skeleton and later content from silently breaking.

**Independent Test**: Open a PR changing a file under `docs/`; the docs build check runs and reports success or failure. A PR touching only PHP code does not trigger it.

**Acceptance Scenarios**:

1. **Given** a PR or a push to `main` changing files under `docs/`, `CHANGELOG.md` or the docs workflow itself, **When** CI runs, **Then** the docs are built from a clean install and the result is reported.
2. **Given** a change touching none of those paths, **When** CI runs, **Then** the docs check is not triggered and existing PHP workflows behave unchanged.

---

### User Story 3 - Contributors know where and how to work on docs (Priority: P3)

A contributor reads the project guidance and learns where the docs live, how to start a local preview, and that EN and DE pages must stay content-equivalent.

**Why this priority**: Keeps later content work consistent, but does not affect the site itself.

**Independent Test**: Read the "Documentation" section in the project guidance and follow it to start a local preview.

**Acceptance Scenarios**:

1. **Given** the project guidance file, **When** a contributor looks for documentation instructions, **Then** a short "Documentation" section states location, local preview command and the EN/DE equivalence rule.

---

### Edge Cases

- A page exists in one language but not the other: must not happen; every planned page exists in both locales.
- Template example pages/assets remain: they must be removed so no unrelated content is published.
- Generated artefacts (installed dependencies, build output, caches) must never be committed.
- The documentation must not ship in the package's distribution archive (relies on issue #1); this feature must not undo that.
- The public site URL is not yet known: a placeholder is used until the deployment issue (#11) sets it.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The repository MUST contain a documentation project in `docs/` with a committed dependency manifest and lock file enabling reproducible clean installs.
- **FR-002**: Installed dependencies, build output and tool caches under `docs/` MUST be git-ignored.
- **FR-003**: The site MUST offer English (default) and German, with content under separate per-language directories and a language switcher.
- **FR-004**: The site title MUST be "erecht24-laravel", with a link to the GitHub repository and per-page "edit" links pointing to the `docs/` folder on the `main` branch.
- **FR-005**: The navigation MUST contain five groups in this order: Getting started, Guides, Usage, Reference, Project; German labels MUST be Erste Schritte, Anleitungen, Verwendung, Referenz, Projekt. Group contents MUST be derived from the page directories.
- **FR-006**: The following pages MUST exist in both `en` and `de`, each with a title, description, sidebar order and a "Coming soon" body (German equivalent in `de`): `index`; getting-started: requirements, installation, quick-start; guides: setup, local-development, queue-and-sync, deployment; usage: blade-components, facade-and-inertia, events, api-client-and-store; reference: configuration, artisan-commands, push-endpoint, exceptions; project: security, troubleshooting, changelog.
- **FR-007**: All template example pages and assets MUST be removed.
- **FR-008**: A CI workflow MUST build the docs from a clean install on pull requests and on pushes to `main` that change `docs/**`, `CHANGELOG.md` or the docs workflow file itself, using the current Node LTS, and MUST NOT deploy.
- **FR-009**: The project guidance file MUST gain a short "Documentation" section covering location, local preview command and the EN/DE content-equivalence rule.
- **FR-010**: Existing PHP CI workflows, package code and the distribution archive contents MUST remain unaffected.
- **FR-011**: The configured site URL MUST be a clearly marked placeholder to be set in the deployment issue (#11).
- **FR-012**: Requesting the site root MUST lead the visitor to the English entry page (`/en/`).

### Key Entities

- **Documentation page**: A page with title, description, sidebar order and body, existing once per language.
- **Navigation group**: One of five top-level sections with an English and a German label, populated from its page directory.
- **Locale**: English (default) or German; owns a full copy of the page tree.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A clean install followed by a build completes without errors locally and in CI.
- **SC-002**: 100% of the 19 planned pages (the index plus 18 group pages) exist in both languages (38 pages) and render without errors.
- **SC-003**: Both language entry pages show all five navigation groups, with German labels on the German site.
- **SC-004**: Docs-relevant pull requests trigger the docs check; PRs touching none of the docs, the changelog or the docs workflow do not, and PHP workflow results are unchanged.
- **SC-005**: The distribution archive of the repository contains zero files below `docs/`.
- **SC-006**: A new contributor finds the docs location and preview command in the project guidance in under 1 minute.

## Assumptions

- Issue #1 (excluding `docs/` from the distribution archive) is already in place or handled separately; this feature only verifies it.
- Page content, deployment, the final site URL and the migration of README content are out of scope (issues #5–#8 and #11).
- "Node LTS" means the current active LTS release at the time the workflow runs.
- German placeholder text is a simple translation of "Coming soon" ("Demnächst verfügbar").
- The site is built as a static site; no server-side features are needed.
