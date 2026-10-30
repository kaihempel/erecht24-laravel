# Data Model

## LegalTextSeal (readonly DTO, `KaiHempel\ERecht24\View`)

| Field | Type | Source |
|---|---|---|
| `url` | string | `asset('vendor/erecht24/'.$file)` |
| `alt` | string | per type and language (de/en) |
| `width` | int | `SealVariant::width()` (intrinsic) |
| `height` | int | `SealVariant::height()` (intrinsic) |
| `displayWidth` | int | `SealVariant::displayWidth()` (300 horizontal / 180 vertical) |

## SealResolver

`for(LegalTextType $type, string $lang): ?LegalTextSeal`

| Type | Setting | File |
|---|---|---|
| Imprint | `imprintSeal()` | `impressum-siegel-{suffix}.webp` |
| PrivacyPolicy | `privacyPolicySeal()` | `datenschutz-siegel-{suffix}.webp` |
| PrivacyPolicySocialMedia | none | returns `null` |

Returns `null` when the setting is `null`. Throws `InvalidConfigurationException` for invalid config. No state, no I/O.
