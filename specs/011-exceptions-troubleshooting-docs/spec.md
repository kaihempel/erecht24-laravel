# Feature Specification: Exceptions Reference and Troubleshooting Pages

**Feature Branch**: `011-exceptions-troubleshooting-docs`

**Created**: 2026-10-14

**Status**: Draft

**Input**: User description: "Add exceptions reference and troubleshooting pages to the Starlight docs (GitHub issue #37). Exception information is spread over several README sections and pitfalls are written as lists of facts rather than answers to concrete problems. Two new bilingual (EN + DE, content-equivalent) pages close the gap: `reference/exceptions` (one section per exception class) and `project/troubleshooting` (Symptom → Cause → Fix). The pitfalls part of `guides/deployment` is shortened to the checklist and links to troubleshooting. Depends on specs 009 and 010."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Fix a concrete problem from its symptom (Priority: P1)

A developer who has deployed the package observes a problem (for example "every push returns 403") and opens the troubleshooting page. They find the entry matching their symptom, learn the cause and apply the fix without reading the rest of the documentation.

**Why this priority**: This is the main value of the issue: turning pitfall facts into answers reachable from a symptom. It currently replaces a "Coming soon" placeholder.

**Independent Test**: For each of the nine listed symptoms, a reader unfamiliar with the package finds the entry by its symptom wording and can state the cause and the fix.

**Acceptance Scenarios**:

1. **Given** a push endpoint that answers `403` on every call, **When** the reader opens the troubleshooting page, **Then** an entry names the symptom and explains the stale secret / config cache cause and the fix.
2. **Given** any troubleshooting entry, **When** it is read, **Then** it is structured as Symptom, Cause, Fix in that order.
3. **Given** the symptoms "push returns 503", "push route returns 404 after changing the push path", "pushes accepted but texts never update", "register refuses the URL", "register refuses a fourth client", "legal text currently not available", "texts differ between web servers" and "eRecht24 cannot reach the push URL", **When** the page is read, **Then** each has its own entry.

---

### User Story 2 - Look up how to handle an exception (Priority: P1)

A developer catches or sees an exception from the package and opens the exceptions reference to learn which class it is, what it extends, when it is thrown and how to handle it.

**Why this priority**: Exception knowledge is currently scattered; a complete reference is the second core deliverable and replaces a placeholder.

**Independent Test**: For each class in the package's exception directory, the page contains a section whose parent class and throw conditions match the source code.

**Acceptance Scenarios**:

1. **Given** the eight exception classes (`Erecht24ApiException`, `Erecht24AuthenticationException`, `MissingConfigurationException`, `InvalidConfigurationException`, `LegalTextStoreException`, `LocalPushUriException`, `TooManyPushClientsException`, `PushClientNotFoundException`), **When** the page is read, **Then** each has exactly one section.
2. **Given** an exception section, **When** it is read, **Then** it states the parent class, when it is thrown and how to handle it.
3. **Given** the page, **When** it is read, **Then** it states that no exception message contains the API key, plugin key or push secret.
4. **Given** `Erecht24AuthenticationException`, **When** its section is read, **Then** it is shown as a subclass of `Erecht24ApiException` thrown for HTTP 401.

---

### User Story 3 - Concise deployment page linking to troubleshooting (Priority: P2)

A developer preparing a deployment follows the checklist on the deployment page. The detailed pitfall explanations no longer clutter it; a link leads to troubleshooting when something goes wrong.

**Why this priority**: Avoids duplicated content and keeps the checklist scannable, but the pages are usable without it.

**Independent Test**: The deployment page contains the checklist and a link to troubleshooting; every pitfall that was on it (config cache, route cache, public HTTPS, three-client limit, multiple servers, new environment/storage) is still covered on the deployment or troubleshooting page.

**Acceptance Scenarios**:

1. **Given** the deployment page, **When** it is read, **Then** the long pitfall explanations are replaced by a link to the troubleshooting page.
2. **Given** any pitfall previously listed under the deployment pitfalls, **When** searching the deployment and troubleshooting pages, **Then** it is found on at least one of them.

---

### User Story 4 - Use both pages in German (Priority: P1)

A German-speaking developer reads the same two pages in German with equivalent content and structure, and the German deployment page matches the English change.

**Why this priority**: The documentation is bilingual and EN/DE must stay content-equivalent; the build fails otherwise.

**Independent Test**: The docs build's parity and content checks pass, and the German pages have the same headings, asides and entry counts as the English ones.

**Acceptance Scenarios**:

1. **Given** the English exceptions and troubleshooting pages, **When** the German counterparts are opened, **Then** they contain full German content (no placeholder) with the same structure.
2. **Given** code identifiers, commands, class names and environment variable names, **When** read in German, **Then** they are unchanged from English.

---

### Edge Cases

- A symptom has several possible causes (e.g. `403` from stale config cache versus wrong secret at eRecht24): the entry covers each cause with its own fix.
- A reader arrives at the deployment page from a search for a pitfall: the link to troubleshooting must make the removed detail easy to find.
- Exception behaviour changes in the code later: the page documents the current source; the throw sites are verified against `src/` at writing time.
- An exception is later added, removed or changed in the source: the build requires a section for every class in the exceptions directory, and the page must state honestly if a class has no throw site rather than invent one.
- Internal links and anchors between the new pages, deployment page and configuration/command pages must resolve in both languages.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The exceptions reference page MUST exist in English and German and contain one section per exception class in the package (all eight).
- **FR-002**: Each exception section MUST state the parent class, when the exception is thrown (verified against call sites in the source) and how to handle it.
- **FR-003**: The exceptions page MUST state that no exception message contains the API key, plugin key or push secret.
- **FR-004**: The troubleshooting page MUST exist in English and German and present every entry as Symptom → Cause → Fix.
- **FR-005**: The troubleshooting page MUST include entries for at least: every push returns 403; push returns 503; push route returns 404 after changing the push path; pushes accepted but texts never update; register refuses the URL (local host / private IP); register refuses a fourth client; "This legal text is currently not available."; texts differ between web servers; eRecht24 cannot reach the push URL (HTTP basic auth, VPN or IP allowlist in front of the environment).
- **FR-006**: Each troubleshooting entry MUST be consistent with the push endpoint reference, the command reference and the actual package behaviour.
- **FR-007**: The deployment pitfalls MUST be reduced to the checklist plus a link to the troubleshooting page, in both languages.
- **FR-008**: Every pitfall of the former deployment section MUST remain covered on the deployment or the troubleshooting page, and every pitfall from the README deployment section likewise.
- **FR-009**: English and German pages MUST be content-equivalent (same file names, same headings/asides counts) and satisfy the existing docs parity and content checks.
- **FR-010**: Internal links MUST use the locale prefix and resolve; new frontmatter MUST follow the existing page contract (no H1, title, description).
- **FR-011**: The documentation site MUST build successfully, as in CI.
- **FR-012**: `CHANGELOG.md` MUST note the change under "Unreleased".

### Key Entities

- **Exception reference entry**: One exception class with its parent class, throw conditions and handling advice.
- **Troubleshooting entry**: One observable symptom with its cause(s) and fix, optionally linking to related reference pages.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 8 of 8 exception classes are documented, with parent class and throw conditions matching the source in 100% of cases.
- **SC-002**: At least 9 troubleshooting symptoms are documented in both languages, each with all three parts (Symptom, Cause, Fix).
- **SC-003**: 100% of former deployment pitfalls remain findable on the deployment or troubleshooting page.
- **SC-004**: A reader can reach the matching troubleshooting entry for a listed symptom within two clicks from the documentation landing page.
- **SC-005**: The documentation build, including parity and content checks, completes without errors.
- **SC-006**: No "Coming soon" placeholder remains on the exceptions or troubleshooting page in either language.

## Assumptions

- The pages replace the existing placeholder files (`reference/exceptions`, `project/troubleshooting`) in `docs/src/content/docs/{en,de}/`; file names and sidebar order stay unchanged.
- German uses the formal "Sie", consistent with the existing German pages.
- Source of pitfall content is the existing deployment page, README and push endpoint reference; no package code changes are in scope.
- Exception details are taken from the current `src/` state (all eight classes currently have a throw site in package code).
- The "push URL unreachable" entry keeps the former deployment pitfall "Public HTTPS" covered once the deployment page is shortened.
- Dependencies #5 and #6 (README/docs content and the Starlight scaffold) are already merged (specs 009 and 010 are done).
