# Feature Specification: Blade Views and Component with Placeholder

**Feature Branch**: `006-blade-views-component`

**Created**: 2026-10-03

**Status**: Draft

**Input**: User description: "Add Blade views and component with placeholder (GitHub issue #9, depends on the legal text store). The package ships Blade templates that wrap the stored eRecht24 HTML and contain a placeholder for the legal text. Users can publish and adapt them (heading, container, styling). The component reads from the legal text store so the API is never called during a page request. Provides a generic legal-text component plus convenience components for imprint, privacy policy and social-media privacy policy; language resolution (explicit lang -> app locale -> first configured language, then first available stored language); a neutral fallback view hinting at `php artisan erecht24:sync` (hint hidden unless debug is on); a `erecht24` view namespace and a publish tag `erecht24-views`."

## Clarifications

### Session 2026-10-03

- Q: How should regional locales like `de_DE` be handled during language resolution? → A: Reduce the locale to its primary language subtag (`de_DE`, `de-AT` → `de`) before matching against configured languages.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Show a stored legal text on a page (Priority: P1)

As a site developer, I want to drop a single tag such as `<x-erecht24::imprint />` into any page and have the stored eRecht24 imprint, privacy policy or social-media privacy policy appear inside a consistent wrapper, so that I can publish legally required pages without writing any rendering code.

**Why this priority**: This is the core value of the feature — turning stored legal text into visible page content. Without it nothing else matters.

**Independent Test**: Store an HTML text for each of the three types, render each convenience tag and the generic tag with an explicit type, and verify the stored HTML appears unescaped (markup intact) inside the wrapper.

**Acceptance Scenarios**:

1. **Given** a stored imprint exists for the resolved language, **When** a page renders the imprint component, **Then** the stored HTML is output with its markup intact (not escaped) inside the wrapper.
2. **Given** stored texts exist for all three types, **When** each convenience component (imprint, privacy policy, social-media privacy policy) is rendered, **Then** each shows the text of its own type.
3. **Given** the generic component is used with a type attribute, **When** it is rendered, **Then** it shows the text of that type, identical to the matching convenience component.
4. **Given** any component is rendered, **When** the page is served, **Then** no request to the eRecht24 API is made; content comes only from local storage.

---

### User Story 2 - Choose the language of the displayed text (Priority: P1)

As a site developer running a multilingual site, I want the displayed text to follow the visitor's language by default, and to be able to force a specific language on a tag, so that each page shows the right legal text and never breaks when a translation is missing.

**Why this priority**: Legal texts are language-specific; showing the wrong language or failing on a missing one is a visible, legally relevant defect.

**Independent Test**: Store texts in German and English, then render with an explicit language, with only the app locale set, and with an unconfigured/missing language, verifying the chosen text in each case.

**Acceptance Scenarios**:

1. **Given** texts in several languages are stored, **When** a component is rendered with an explicit language attribute, **Then** that language is shown regardless of the application locale.
2. **Given** no language attribute is given, **When** a component is rendered, **Then** the text for the current application locale is shown; a regional locale such as `de_DE` matches its primary language `de`.
3. **Given** neither a language attribute nor a configured-and-matching application locale applies, **When** a component is rendered, **Then** the first configured language is used.
4. **Given** the resolved language is not a configured language, or no stored file exists for it, **When** a component is rendered, **Then** the first available stored language for that type is shown instead.

---

### User Story 3 - Clear fallback when no text exists (Priority: P1)

As a site developer, I want a neutral placeholder instead of a blank area or an error when no legal text has been stored yet, so that a missing text is noticed and the page never crashes.

**Why this priority**: A silently empty imprint page is a compliance risk; an exception on a public page is worse. This must hold from day one.

**Independent Test**: Render each component with an empty store, once with debug on and once with debug off, and verify neutral fallback output, a hint only in debug mode, and no exception.

**Acceptance Scenarios**:

1. **Given** no text of the requested type is stored in any language, **When** a component is rendered, **Then** a neutral fallback view is shown rather than empty output or an error.
2. **Given** the fallback is shown and debug mode is on, **When** the page renders, **Then** it includes a hint to run `php artisan erecht24:sync`.
3. **Given** the fallback is shown and debug mode is off (production), **When** the page renders, **Then** the sync hint is not present in the output.

---

### User Story 4 - Customize the markup (Priority: P2)

As a site developer, I want to publish the package's views into my application and edit them (heading, container, styling), so that the legal pages match my site's design and my edits override the package defaults.

**Why this priority**: Important for adoption, but the feature is already usable with default markup.

**Independent Test**: Publish the views using the `erecht24-views` tag, change the wrapper markup in the published copy, render a component, and verify the changed markup is used.

**Acceptance Scenarios**:

1. **Given** the package is installed, **When** the developer publishes the `erecht24-views` tag, **Then** all package views are copied to the application's vendor view folder for `erecht24`.
2. **Given** a published copy of a view exists in the application, **When** a component is rendered, **Then** the published copy is used instead of the package default.
3. **Given** the three per-type views are thin wrappers around the generic wrapper, **When** a developer edits only the generic wrapper, **Then** all three types reflect the change.

---

### Edge Cases

- Requested language exists in the store but is not in the configured languages list: treated as not configured and falls back to the first available stored language.
- Stored file for the resolved language is missing but another language exists: the other language is shown, not the fallback view.
- Stored text exists but is empty: treated as missing and the fallback view is shown, never a silently blank area.
- Application locale carries a regional variant (e.g. `de_DE`, `de-AT`): reduced to its primary language subtag (`de`) before matching. A locale whose primary subtag is not configured follows the not-configured fallback.
- Component used with an unknown type value: fails with a clear developer-facing error at render time rather than showing an unrelated text.
- Stored HTML contains markup/script: rendered as-is because its source is the trusted eRecht24 API; this trust assumption is documented.
- Store storage disk is unreachable or unreadable: the page shows the fallback view rather than throwing.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The package MUST provide a generic legal-text component that accepts a text type and an optional language and renders the stored text inside a shared wrapper. The type identifies one of the three legal texts and MUST be accepted both as its API identifier (`imprint`, `privacyPolicy`, `privacyPolicySocialMedia`) and as its URL-style name (`imprint`, `privacy-policy`, `privacy-policy-social-media`).
- **FR-002**: The package MUST provide convenience components for imprint, privacy policy and social-media privacy policy that behave like the generic component with the type preset and accept the same optional language.
- **FR-003**: The wrapper MUST output the stored HTML unescaped at a single clearly marked placeholder, and the documentation MUST state that the content comes from the trusted eRecht24 API and is not sanitized.
- **FR-004**: Each of the three types MUST have its own thin view that delegates to the shared wrapper, so developers can customize all types by editing one file or one type by editing its own.
- **FR-005**: The language MUST be resolved in order: explicit language attribute, then application locale, then first configured language. Locale values (attribute or application) MUST be reduced to their primary language subtag (e.g. `de_DE` → `de`) before matching against configured languages.
- **FR-006**: If the resolved language is not a configured language, or no stored text exists for it, the system MUST fall back to the first available stored language for that type, checking languages in the order they are configured.
- **FR-007**: If no text exists for the type in any language (or the stored text is empty or cannot be read), the system MUST render a neutral fallback view instead of empty output or an exception.
- **FR-008**: The fallback view MUST show a hint to run `php artisan erecht24:sync` only when application debug mode is enabled; otherwise the hint MUST NOT appear.
- **FR-009**: Rendering any component MUST NOT trigger any HTTP request; content is read only from local storage.
- **FR-010**: The package MUST register a view namespace `erecht24` so components and views are addressable as `erecht24::…`.
- **FR-011**: The package MUST offer a publish tag `erecht24-views` that copies all package views to the application's `vendor/erecht24` view folder, and published copies MUST take precedence over package views.
- **FR-012**: An invalid type given to the generic component MUST produce a clear error identifying the allowed types.
- **FR-013**: Automated tests MUST cover every language and fallback path: explicit language, app locale, regional locale (`de_DE`), first configured language, unconfigured language, missing file, empty store (debug on and off), and publish override.
- **FR-014**: Any additional HTML attributes given on a component tag (e.g. `class`) MUST be applied to the wrapper element, so developers can style the output without publishing views.

### Key Entities

- **Legal text**: stored HTML for one type and language, with fetched-at and source-modified timestamps. Read-only here.
- **Text type**: imprint, privacy policy, or social-media privacy policy.
- **Language**: a configured language code (`de`, `en`), possibly different from the visitor's locale.
- **Wrapper view**: the shared container with the HTML placeholder; the main customization point.
- **Fallback view**: neutral output shown when no text is available.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A developer can show any of the three legal texts on a page with a single tag and no additional code (1 line of markup).
- **SC-002**: For every combination of type and available language, the displayed text matches the specified language order in 100% of test cases.
- **SC-003**: With an empty store, 100% of renders produce non-empty, error-free output, and the sync hint appears only when debug mode is on.
- **SC-004**: Zero outbound HTTP requests occur across all rendering scenarios.
- **SC-005**: A developer can publish the views and see a markup customization take effect in under 2 minutes without modifying package files.
- **SC-006**: Every fallback path in the requirements is covered by at least one automated test.

## Assumptions

- Depends on the existing legal text store (spec 003) for reading stored HTML and on the sync feature/commands (specs 004, 005) to populate it; this feature does not fetch or write texts.
- Supported languages are those in the package configuration (currently `de`, `en`); the first configured language is the default.
- Stored HTML is trusted (eRecht24 API) and intentionally not escaped or sanitized; applications needing stricter handling can customize the published wrapper.
- The default wrapper markup is minimal and unstyled so it adapts to any site design.
- The fallback view text is a neutral, untranslated-by-default message that developers can override via published views.
- Caching of rendered output is out of scope; reading the stored file per render is acceptable.
