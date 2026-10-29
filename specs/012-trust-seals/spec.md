# Spec 012: Trust seal settings (#59)

Applications choose via `.env` which eRecht24 trust seal variant is shown for the imprint and the privacy policy. This spec covers configuration and validation only; rendering is tracked separately.

## Requirements

- `SealVariant` enum: `light-horizontal`, `light-vertical`, `dark-horizontal`, `dark-vertical` with `isVertical()`, `width()`, `height()`, `displayWidth()`, `fileSuffix()`.
- Config `erecht24.seals.imprint` (`ERECHT24_IMPRINT_SEAL`) and `erecht24.seals.privacy_policy` (`ERECHT24_PRIVACY_POLICY_SEAL`), unset by default.
- `Erecht24Settings::imprintSeal()` / `privacyPolicySeal()` return `?SealVariant`: null/empty/whitespace → `null`; trimmed and lowercased otherwise; unknown → `InvalidConfigurationException` naming `seals.<key>` and all allowed values.
- Validation is lazy (on accessor call), never at boot.

## Acceptance

- Defaults return `null`; `Light-Horizontal ` resolves to `LightHorizontal`; `blue` throws.
- Horizontal 1425×600 (display 300), vertical 900×1400 (display 180).
- No regressions; lint and PHPStan pass.
