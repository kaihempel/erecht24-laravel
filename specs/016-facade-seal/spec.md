# Feature Specification: Facade seal accessor

**Feature Branch**: `feature/facade-seal`
**Created**: 2026-11-21
**Status**: Implemented

**Input**: Applications that render legal texts through the `ERecht24` facade (Inertia, API responses) must be able to show the configured trust seal.

## Background

Specs 012 to 014 added the trust seals, but only the Blade components resolve and render them (`AbstractLegalTextComponent` → `SealResolver`). An application that passes `ERecht24::html()` / `resolve()` to an Inertia page sets `ERECHT24_IMPRINT_SEAL` / `ERECHT24_PRIVACY_POLICY_SEAL`, publishes the images and still sees no seal, because the facade offers no way to get it. The docs described the settings without saying that they only take effect in Blade.

## User Scenarios & Testing

### User Story 1 - Show the seal on an Inertia page (Priority: P1)

A developer renders the imprint through the facade and wants the configured seal below it.

**Acceptance Scenarios**:

1. **Given** `erecht24.seals.imprint` is set, **When** `ERecht24::seal('imprint')` is called, **Then** it returns a `LegalTextSeal` with the same URL, alt text and display size that `<x-erecht24::imprint>` renders.
2. **Given** no seal is configured, or the type is the social-media privacy policy, **When** `seal()` is called, **Then** it returns `null`.
3. **Given** an invalid seal setting, **When** `seal()` is called, **Then** it returns `null`, logs a warning and does not throw.
4. **Given** no text is stored, **When** `seal()` is called, **Then** it still returns the configured seal and performs no HTTP request.
5. **Given** an unknown type string, **When** `seal()` is called, **Then** it throws `\InvalidArgumentException` like the other manager methods.

## Requirements

- **FR-001**: `Erecht24Manager::seal(LegalTextType|string $type, ?string $lang = null): ?LegalTextSeal` MUST delegate to `SealResolver` and be exposed on the `ERecht24` facade.
- **FR-002**: `$lang` MUST only select the alt text and default to the app locale; `de` and its regional variants (`de_DE`, `de-AT`) select German, everything else English.
- **FR-003**: `seal()` MUST NOT read the store or call the API. Showing the seal only below a stored text is the caller's decision (`has()` / `resolve()`).
- **FR-004**: A cosmetic setting MUST never take down a legal page: `InvalidConfigurationException` is caught, logged as a warning and turned into `null`, matching the Blade components.
- **FR-005**: `LegalTextSeal::displayHeight()` MUST return the height the Blade view renders; the Blade output stays byte-identical.
- **FR-006**: Docs (EN/DE) MUST state that only the Blade components render the seal automatically and show the facade usage, including the asset publish step.

## SemVer impact

MINOR. New method and new `LegalTextSeal::displayHeight()`. `Erecht24Manager`'s constructor gains a fourth argument (`SealResolver`); the manager is resolved from the container, so this only affects code that instantiates it manually.

## Success Criteria

- **SC-001**: An Inertia application can render the configured seal with data from the facade alone.
- **SC-002**: Tests fail if `seal()` throws on an invalid setting, depends on a stored text, or diverges from the Blade output.
