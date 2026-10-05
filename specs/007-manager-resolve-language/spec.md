# Feature Specification: Expose the Resolved Legal Text Language

**Feature Branch**: `007-manager-resolve-language`

**Created**: 2026-10-05

**Status**: Draft

**Input**: GitHub issue #27 — "[FEAT] Add public `Erecht24Manager::resolve()` exposing the resolved language". Today the package's programmatic access returns only the text content. When a visitor asks for English and only German is stored, the package silently falls back to German and the calling application cannot tell. Consuming applications (e.g. Kuweh, languages `de` and `en`) need the language that was actually delivered so they can mark the page language correctly and show an "only available in German" notice. The change is purely additive; existing access methods stay unchanged.

## User Scenarios & Testing *(mandatory)*

The "users" of this feature are developers of applications that consume the package (for example via Inertia), and indirectly their website visitors.

### User Story 1 - Know which language was actually delivered (Priority: P1)

As an application developer, I request a legal text (imprint, privacy policy, social-media privacy policy) for a language and receive, together with the content, the language the content is actually written in and the language I asked for, so I can set the page's language attribute correctly.

**Why this priority**: Without the delivered language, a German text can be served on an English page with a wrong language marker, which harms accessibility and SEO. This is the core value of the issue.

**Independent Test**: Store German and English texts, request the English imprint, and verify the result contains the English content, reports `en` as delivered language and `en` as requested language.

**Acceptance Scenarios**:

1. **Given** German and English imprints are stored and the application locale is `en`, **When** the developer resolves the imprint without an explicit language, **Then** the result contains the English content, the delivered language `en` and the requested language `en`.
2. **Given** German and English imprints are stored, **When** the developer resolves the imprint with explicit language `de`, **Then** the delivered language is `de` and the requested language is `de`.
3. **Given** no text of the requested type is stored in any language, **When** the developer resolves it, **Then** no result is returned (empty / "nothing available").

---

### User Story 2 - Detect a language fallback (Priority: P1)

As an application developer, I can tell whether the delivered text is a fallback (a different language than requested), so I can show visitors a notice such as "This text is only available in German".

**Why this priority**: The fallback notice is the concrete product need of the consuming application; it is equally essential as the delivered language and builds on the same result.

**Independent Test**: Store only the German imprint, set the application locale to `en`, resolve the imprint through the language resolver and verify the result reports delivered `de`, requested `en`, and fallback = true.

**Dependency**: The fallback rule itself is testable at resolver/value-object level without User Story 1. The facade-level checks (`ERecht24::resolve()`) build on User Story 1's public method and therefore run after it.

**Acceptance Scenarios**:

1. **Given** only the German imprint is stored and the application locale is `en`, **When** the developer resolves the imprint, **Then** the delivered language is `de`, the requested language is `en`, and the result is marked as fallback.
2. **Given** only the German imprint is stored, **When** the developer resolves the imprint with explicit language `en`, **Then** the result is marked as fallback.
3. **Given** the application locale is the regional variant `en-GB` (or `en_GB`) and English is stored, **When** the developer resolves a text, **Then** the requested language is reported as `en` and the result is not marked as fallback.
4. **Given** German and English imprints are stored, **When** the developer resolves the imprint with the application locale `en` or with explicit language `de`, **Then** the result is not marked as fallback.

---

### User Story 3 - Documentation and release notes (Priority: P2)

As an application developer evaluating or upgrading the package, I find the new capability documented in both the English and German README (in the facade / Inertia section, including a short example of reading the delivered language and the fallback flag) and listed in the changelog.

**Why this priority**: The capability is only useful if developers can discover it; it does not affect runtime behavior.

**Independent Test**: Read the "Facade and Inertia" section of both READMEs and the changelog's "Unreleased / Added" section and verify the new method is described consistently.

**Acceptance Scenarios**:

1. **Given** the English README, **When** a developer reads the facade / Inertia section, **Then** they find a table row for the new method and a short example using the delivered language and the fallback flag.
2. **Given** the German README, **When** a developer reads the equivalent section, **Then** it contains content-equivalent information.
3. **Given** the changelog, **When** a developer reads "Unreleased / Added", **Then** the new method is listed.

---

### Edge Cases

- **Requested language not configured** (e.g. `fr` while only `de`/`en` are configured): the requested language is still reported as `fr` (normalized), the text is delivered in the fallback language, and the result is marked as fallback.
- **No explicit language and no usable application locale**: the requested language is unknown (empty), and the result is never marked as fallback.
- **Explicit language given that differs from the application locale**: the explicit language wins as the requested language.
- **Requested language with mixed case or whitespace** (e.g. ` EN `): normalized to `en` before comparison.
- **Unknown legal text type name**: rejected with an "invalid argument" error, consistent with the existing access methods.
- **Stored text unreadable or empty**: treated as missing for that language (existing behavior), which can lead to a fallback or to no result.
- **Invalid language configuration**: no result is returned (existing behavior), no error is thrown.
- **Remote API**: resolving never contacts the eRecht24 API; it reads stored texts only.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The package MUST offer a public way (on the manager and via the facade) to resolve a legal text by type and optional language that returns either nothing (no text available) or a result containing the content, the delivered language and the requested language.
- **FR-002**: The result MUST offer a fallback indicator that is true exactly when a requested language is known and differs from the delivered language; otherwise false.
- **FR-003**: The requested language MUST be determined once per resolution as the normalized explicit language if given, otherwise the normalized application locale; normalization uses the lowercase primary language subtag (e.g. `en-GB` → `en`).
- **FR-004**: The language selection order (explicit → application locale → first configured → any other stored configured language) MUST remain unchanged.
- **FR-005**: The legal text type MUST be accepted either as the type enumeration or as its string name; unknown string names MUST be rejected with an invalid-argument error, as for the existing methods.
- **FR-006**: Resolving MUST read only stored texts and MUST NOT make any request to the eRecht24 API.
- **FR-007**: The existing methods for content (`html`), availability (`has`), last-modified date (`lastModified`) and configured languages (`languages`) MUST behave exactly as before; existing tests stay unchanged and pass.
- **FR-008**: Existing code that constructs the result object with only content and language MUST keep working (the requested language is optional and defaults to unknown).
- **FR-009**: The result object MUST be part of the package's public API (not marked internal); the underlying language resolver remains internal.
- **FR-010**: The facade's documented method list MUST include the new method so IDEs and static analysis recognize it.
- **FR-011**: Both READMEs (English and German, content-equivalent) MUST document the new method in the facade / Inertia section with a table row and a short example; the changelog MUST list it under "Unreleased / Added".
- **FR-012**: Automated tests MUST cover: both languages stored with locale `en` (delivered `en`, no fallback); only `de` stored with locale `en` (delivered `de`, requested `en`, fallback); regional locale `en-GB` normalized to `en`; nothing stored (no result); unknown type name (invalid-argument error); no outgoing API requests.
- **FR-013**: The change MUST pass the project's lint, static analysis (level 8) and test suite.

### Key Entities

- **Resolved legal text**: The outcome of resolving a legal text. Attributes: content (trusted HTML from eRecht24), delivered language (code of the stored text actually returned), requested language (normalized code that was asked for, may be unknown), and a derived fallback indicator.
- **Legal text type**: One of imprint, privacy policy, social-media privacy policy; identified by enumeration or string name.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: In 100% of tested scenarios, a consuming application can determine the delivered language of a legal text with a single call, without extra lookups.
- **SC-002**: The fallback indicator is correct in 100% of the tested combinations (both stored / only German stored / regional locale / unconfigured requested language / no requested language).
- **SC-003**: 0 existing tests need to be modified, and all existing tests pass after the change (no behavior change for existing consumers).
- **SC-004**: 0 outgoing requests to the eRecht24 API occur while resolving texts in the test suite.
- **SC-005**: Both READMEs and the changelog describe the new capability; the README pair remains content-equivalent.
- **SC-006**: Lint, static analysis and the full test matrix pass in CI.

## Assumptions

- The release is a MINOR version bump; no breaking change for existing consumers.
- The requested language is reported even when it is not one of the configured languages (e.g. `fr`), so that a fallback is still detectable; this follows the issue's rule "fallback whenever delivered differs from normalized requested".
- When neither an explicit language nor an application locale yields a requested language, the result is never considered a fallback.
- The Blade components and their rendering stay unchanged; they may continue to ignore the requested language.
- Supported languages remain `de` and `en` as defined by the existing configuration.
- Scope excludes any new storage, synchronization or HTTP behavior.
