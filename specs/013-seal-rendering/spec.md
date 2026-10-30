# Feature Specification: Render the trust seal below imprint and privacy policy

**Feature Branch**: `013-seal-rendering`

**Created**: 2026-10-29

**Status**: Draft

**Input**: GitHub issue #61 — "Render the seal centered below imprint and privacy policy text" (depends on #59 seal settings, spec 012, and the published seal image assets).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Seal below the imprint and privacy policy (Priority: P1)

A site owner has chosen a seal variant for the imprint and/or the privacy policy and has published the seal images. Visitors who open the legal page see the eRecht24 seal centered directly below the legal text, regardless of whether the page uses the dedicated imprint/privacy-policy tags or the generic legal-text tag.

**Why this priority**: This is the visible outcome of the whole seal effort; without it the configured seal has no effect.

**Independent Test**: Configure a seal variant for the imprint, store imprint text, render the imprint tag, and verify the legal text is followed by a centered seal image pointing to the matching published image.

**Acceptance Scenarios**:

1. **Given** the imprint seal is set to `light-horizontal` and imprint text is stored, **When** the imprint is rendered, **Then** the text is followed by a centered image pointing to `vendor/erecht24/impressum-siegel-light-horizontal-large.webp`, inside the same wrapper as the text.
2. **Given** the privacy-policy seal is set to `dark-vertical`, **When** the privacy policy is rendered, **Then** a centered image for `datenschutz-siegel-dark-vertical-large.webp` appears with intrinsic size 900×1400 and a displayed width of 180 px.
3. **Given** a horizontal variant is configured, **When** rendered, **Then** the displayed width is 300 px and never exceeds the width of its container.
4. **Given** a seal is configured for the imprint or privacy policy, **When** the generic legal-text tag is used with the matching type, **Then** the seal is rendered identically.

---

### User Story 2 - No seal where none is wanted (Priority: P1)

Pages without a configured seal, the social-media privacy policy, and the "text missing" placeholder never show a seal, and existing sites see no change in their output.

**Why this priority**: Backward compatibility and legal correctness; an unwanted seal on the wrong text would be wrong.

**Independent Test**: Render each text without configuration, render the social-media privacy policy with both seals configured, and render a missing text; verify no seal image appears and, for the unconfigured case, output is identical to before.

**Acceptance Scenarios**:

1. **Given** no seal is configured, **When** any legal text is rendered, **Then** the output is byte-identical to the output before this feature.
2. **Given** both seals are configured, **When** the social-media privacy policy is rendered, **Then** no seal image appears.
3. **Given** a seal is configured but no text is stored, **When** rendered, **Then** the "missing" placeholder is shown without a seal.

---

### User Story 3 - Seal description matches page language (Priority: P2)

Visitors and assistive technologies receive an image description in the language of the rendered legal text.

**Why this priority**: Accessibility and consistency, but the seal is useful without it.

**Independent Test**: Render the imprint and privacy policy in German and English and compare the image descriptions.

**Acceptance Scenarios**:

1. **Given** German text is rendered, **Then** the descriptions are "Impressum – rechtssicher mit eRecht24" (imprint) and "Datenschutzerklärung – rechtssicher mit eRecht24" (privacy policy).
2. **Given** English text is rendered, **Then** the descriptions are "Legal notice – legally compliant with eRecht24" and "Privacy policy – legally compliant with eRecht24".

---

### User Story 4 - Existing integrations keep working (Priority: P2)

Developers who customised the published legal-text view, or who instantiate the components directly, are not broken by the update.

**Why this priority**: Protects existing installations (constitution: backward-compatible public API).

**Independent Test**: Publish the views, replace the legal-text view with one that ignores the seal, render, and verify no error; check component constructors are unchanged.

**Acceptance Scenarios**:

1. **Given** a published legal-text view that does not know about the seal, **When** a legal text is rendered, **Then** it renders without errors (without a seal).
2. **Given** the update, **Then** no public constructor signature of the imprint, privacy-policy, social-media privacy-policy or generic legal-text components has changed.

### Edge Cases

- Invalid seal configuration value: rendering fails with the existing invalid-configuration error (wrapped by the view layer), naming the offending setting.
- Fallback language: the seal description follows the language actually rendered, not the requested one.
- Rendering never triggers an HTTP request.
- Seal configured but images not yet published: the image URL is still emitted (publishing is the site owner's responsibility, spec of #2).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST render the configured seal below the legal text, horizontally centered, inside the legal text wrapper, for the imprint and the privacy policy.
- **FR-002**: The system MUST NOT render a seal for the social-media privacy policy, even if both seals are configured.
- **FR-003**: The imprint MUST use the imprint seal setting and the `impressum-siegel-<variant>.webp` image; the privacy policy MUST use the privacy-policy seal setting and the `datenschutz-siegel-<variant>.webp` image, both served from the published `vendor/erecht24/` asset location.
- **FR-004**: The seal image MUST declare its intrinsic width and height, display at the variant's display width (300 px horizontal, 180 px vertical), never exceed its container width, keep its aspect ratio, and load lazily.
- **FR-005**: The seal MUST be centered without requiring any CSS framework (self-contained styling).
- **FR-006**: The seal description MUST be German or English matching the rendered language, with the texts listed in the user stories.
- **FR-007**: The "missing" placeholder MUST never show a seal.
- **FR-008**: Without seal configuration, rendered output MUST be byte-identical to the output before this feature.
- **FR-009**: Seal resolution MUST be available as a reusable service returning a seal description (URL, description, intrinsic size, display width) or nothing; the shared view receives it as optional data.
- **FR-010**: The generic legal-text tag and all three dedicated tags MUST show the seal consistently (dedicated views forward it to the shared view).
- **FR-011**: Existing public component constructors MUST NOT change, and user-published legal-text views unaware of the seal MUST still render.
- **FR-012**: Invalid seal configuration MUST surface as the existing invalid-configuration error at render time; rendering MUST perform no HTTP request.
- **FR-013**: The change MUST be covered by feature tests for all scenarios above, including the published-view override case, and pass tests, lint and static analysis.

### Key Entities

- **Legal text seal**: Immutable description of the seal to show — image URL, description, intrinsic width and height, display width.
- **Seal resolver**: Decides, for a legal text type and language, which seal (if any) applies.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: For every configured seal variant (4) and both seal-bearing text types (2), the rendered page contains exactly one seal with the correct image, size and language description.
- **SC-002**: In 100% of renders of the social-media privacy policy, missing placeholders and unconfigured sites, no seal appears and unconfigured output is unchanged.
- **SC-003**: Zero existing tests regress; zero public constructor signature changes; a previously published custom view still renders.
- **SC-004**: Seals never overflow narrow containers: displayed width is capped at the container width.

## Assumptions

- Seal configuration and validation (spec 012) and publishing of the seal image files (issue #2) are available; this feature only renders.
- Only German and English are supported languages, matching the package.
- Images live under the published `vendor/erecht24/` public path.
- The seal is decorative branding of trusted content; no extra consent handling is needed.
