# Plan: Trust seal settings

1. Add `src/Enums/SealVariant.php`.
2. Add the `seals` block to `config/erecht24.php`.
3. Add `imprintSeal()`, `privacyPolicySeal()` and private `seal(string $key)` to `Erecht24Settings`.
4. Tests: `Erecht24SettingsSealsTest`, `SealVariantTest`, extend config defaults/env override tests.
5. Document env variables on the EN/DE configuration pages; update `CHANGELOG.md`.
