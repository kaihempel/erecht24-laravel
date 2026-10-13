# Feature Specification: Move English README Content into Starlight Pages

**Feature Branch**: `009-readme-to-starlight-en`

**Created**: 2026-10-13

**Status**: Draft

**Input**: User description: "Move the English README content into Starlight pages (GitHub issue #35, depends on spec 008 scaffold). Split the README into the 16 placeholder pages of the English docs site, keeping the reviewed text as close as possible, adapting structure, links and presentation only."

## Clarifications

### Session 2026-10-13

- Q: Where do the README "Pitfalls" go, given the issue maps only the checklist to the deployment page? → A: Keep checklist and pitfalls together on `guides/deployment`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Find every README topic on the docs site (Priority: P1)

A developer evaluating or installing the package opens the English documentation site and finds, on dedicated pages, all the information that today lives in the README: requirements, installation, quick start, setup, local development, queue and sync, deployment, usage, reference and security.

**Why this priority**: The site is only useful once the placeholder pages carry real content. This is the core deliverable.

**Independent Test**: Walk through the README section by section and confirm each one (except Development and License) appears on exactly one mapped page, with text equivalent to the README.

**Acceptance Scenarios**:

1. **Given** the README sections Requirements, Installation, Quick start, **When** a reader opens the "Getting started" pages, **Then** each section is present on its own page with the README's wording.
2. **Given** the README sections on setup, push URL requirements, queue worker/initial sync and the deployment checklist, **When** a reader opens the "Guides" pages, **Then** each appears on its mapped page (setup, local development, queue and sync, deployment).
3. **Given** the README Usage subsections, **When** a reader opens the "Usage" pages, **Then** Blade components plus Customizing the views, Facade and Inertia, `LegalTextUpdated` events, and API client and store each appear on their own page.
4. **Given** the README sections Configuration, Artisan commands, Push endpoint, Security and Disclaimer, **When** a reader opens the "Reference" and "Project" pages, **Then** they appear on the configuration, artisan-commands, push-endpoint and security pages respectively.
5. **Given** the README intro and four-step flow, **When** a reader opens the landing page, **Then** they see a hero and the flow presented as a visual step/card layout.
6. **Given** the README sections Development and License, **When** the docs pages are reviewed, **Then** neither appears on the site.

---

### User Story 2 - Navigate by site links, not README anchors (Priority: P1)

A reader following a cross-reference inside the docs lands on the right site page, never on the README or a dead anchor.

**Why this priority**: Broken or README-pointing links defeat the purpose of the site.

**Independent Test**: Build the docs site and check that the build reports no broken internal links; search the pages for README references.

**Acceptance Scenarios**:

1. **Given** any internal cross-reference, **When** a reader clicks it, **Then** it opens the corresponding site page (e.g. the configuration reference).
2. **Given** all English pages, **When** searched for links to `README.md` or README anchors, **Then** none are found.
3. **Given** the docs build, **When** it runs, **Then** it succeeds with no broken-link or missing-page errors.

---

### User Story 3 - Notice critical warnings and tips (Priority: P2)

A reader skimming a page cannot miss the important caveats: config/route caching, public HTTPS, trusted unescaped HTML, push secret handling, and the sync-queue option for small sites.

**Why this priority**: These warnings prevent deployment and security mistakes; presentation matters but the content already exists.

**Independent Test**: Open each page containing these topics and confirm the right callout type is used.

**Acceptance Scenarios**:

1. **Given** the config/route cache and public HTTPS notes, **When** displayed, **Then** they use the caution callout.
2. **Given** the trusted unescaped HTML and push secret handling notes, **When** displayed, **Then** they use the danger callout.
3. **Given** the `QUEUE_CONNECTION=sync` note for small sites, **When** displayed, **Then** it uses the tip callout.

---

### User Story 4 - Follow setup and framework variants easily (Priority: P2)

A reader follows the setup procedure as numbered steps and picks their frontend (React or Vue) for the Inertia example via tabs.

**Why this priority**: Improves readability but does not change the information.

**Independent Test**: Open the setup and facade/Inertia pages and confirm the step and tab presentations.

**Acceptance Scenarios**:

1. **Given** the setup page, **When** opened, **Then** the numbered procedure is rendered as steps.
2. **Given** the facade-and-Inertia page, **When** opened, **Then** React and Vue rendering examples are offered as tabs.

---

### User Story 5 - Look up every configuration variable (Priority: P2)

A developer looks up an environment variable and finds the complete table, each variable name in backticks, plus the settings that have no environment variable.

**Why this priority**: The README environment reference is guarded by an automated test, and a downstream issue depends on every variable being present in backticks.

**Independent Test**: Compare the variables defined in the package configuration with the backticked names on the configuration page.

**Acceptance Scenarios**:

1. **Given** the package configuration, **When** compared to the configuration page, **Then** every `ERECHT24_*` variable appears there in backticks.
2. **Given** the configuration page, **When** read, **Then** settings without an environment variable are documented as in the README.

---

### Edge Cases

- Pages reserved for later work (troubleshooting, exceptions, changelog) remain untouched placeholders; links to them must not be introduced as broken links.
- The README "Deployment checklist and pitfalls" section stays whole on the deployment page (checklist and pitfalls); the pitfalls must not be duplicated onto other pages.
- README H1 and "Contents" table of contents have no counterpart; the site navigation replaces them.
- Code samples, tables and inline code in the README must survive the move intact (including characters that are special in MDX such as braces and angle brackets).
- The README stays unchanged (shortening is a separate issue), so README and docs temporarily duplicate content.
- German pages are not changed here; the structural mismatch between EN and DE during the transition is accepted.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The English landing page MUST present the README intro as a hero and the four-step flow as a step or card layout.
- **FR-002**: The Requirements, Installation and Quick start README sections MUST each be placed on their own Getting started page.
- **FR-003**: The setup procedure MUST be placed on the setup guide as numbered steps; push URL requirements, queue worker/initial sync, and the deployment checklist together with its pitfalls MUST be placed on the local development, queue and sync, and deployment guides respectively.
- **FR-004**: Blade components together with Customizing the views, Facade and Inertia, `LegalTextUpdated`, and API client and store MUST each be placed on their mapped Usage page; the Inertia page MUST offer React and Vue examples as tabs.
- **FR-005**: The Configuration (env table and non-env settings), Artisan commands and Push endpoint sections MUST be placed on their mapped Reference pages.
- **FR-006**: The Security and Disclaimer sections MUST be placed together on the security project page.
- **FR-007**: Every README section except Development and License MUST appear on exactly one page.
- **FR-008**: Page text MUST stay as close as possible to the existing README wording; only structure, links and presentation may change.
- **FR-009**: No page MAY contain a top-level heading in its body; every page MUST have a title and a description; H2/H3 structure below MUST be preserved.
- **FR-010**: All internal links MUST point to site paths (e.g. `/en/reference/configuration/`); no page MAY link to `README.md` or README anchors.
- **FR-011**: Config/route cache and public HTTPS notes MUST use the caution callout; trusted unescaped HTML and push secret handling MUST use the danger callout; the `QUEUE_CONNECTION=sync` note for small sites MUST use the tip callout.
- **FR-012**: The configuration page MUST list every `ERECHT24_*` variable defined in the package configuration, each in backticks.
- **FR-013**: The troubleshooting, exceptions and changelog pages MUST NOT be modified.
- **FR-014**: The README, the German pages and package source code MUST NOT be modified.
- **FR-015**: The docs build MUST succeed locally and in CI with no broken internal links.

### Key Entities

- **README section**: A heading-delimited block of the existing README; maps to exactly one docs page (or is excluded: Development, License).
- **Docs page**: An English site page with a title, description, body and sidebar position, grouped as Getting started, Guides, Usage, Reference or Project.
- **Callout**: A caution, danger or tip highlighted block used for warnings and hints.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of README sections other than Development and License are found on exactly one English docs page (16 of 16 mapped pages carry content).
- **SC-002**: The docs build completes with zero broken-link errors and zero missing-page errors.
- **SC-003**: Zero English pages contain a body top-level heading or a link to the README.
- **SC-004**: 100% of `ERECHT24_*` variables in the package configuration appear in backticks on the configuration page.
- **SC-005**: A reader can reach any topic from the landing page within three clicks using the sidebar and cross-links.
- **SC-006**: All warning topics named in FR-011 are shown with the specified callout type (0 deviations).

## Assumptions

- The scaffold from spec 008 exists with the 16 English placeholder pages; pages needing steps, tabs or cards (landing, setup, facade-and-Inertia) are renamed to the `.mdx` extension while keeping their URLs.
- Prose is copied from the README; wording changes are limited to what link rewriting and callout conversion require.
- Existing sidebar ordering and page frontmatter from the scaffold are retained.
- The README stays unchanged until a separate issue shortens it; the README environment-variable test continues to guard the README only.
- German content migration is a separate issue.
- Supporting changes outside the page files are in scope: the docs check scripts and `docs/package.json`, `CHANGELOG.md` and the Documentation section of `CLAUDE.md`. Forbidden remains: README files, German pages, reserved pages and package source.
