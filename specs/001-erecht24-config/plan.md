# Implementation Plan: eRecht24 Package Configuration & Environment Handling

**Branch**: `001-erecht24-config` | **Date**: 2026-10-01 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-erecht24-config/spec.md`

## Summary

Add a publishable `config/erecht24.php` backed by `ERECHT24_*` environment variables, and a typed `Erecht24Settings` value object that is the package's single read path for configuration. The value object normalizes and validates `text_languages` (restricted to `de`/`en`), `push_path` (single leading slash, no trailing slash) and `timeout` (positive integer), and lazily throws descriptive, distinct exceptions for invalid values (`InvalidConfigurationException`) versus missing required credentials (`MissingConfigurationException` for `api_key`, `plugin_key`, `push_secret`) only at the point of use, never at boot.

## Technical Context

**Language/Version**: PHP 8.3 (per `composer.json` `require.php: ^8.3`), `declare(strict_types=1)` throughout.

**Primary Dependencies**: `illuminate/support` ^12.0|^13.0 (ServiceProvider, config repository), `illuminate/console` (artisan `vendor:publish`); no new Composer dependencies required.

**Storage**: N/A — configuration only; `disk`/`directory` settings are passed through as values for a later feature to consume, not read/written here.

**Testing**: Pest 3/4 via `orchestra/testbench`, run with `composer test`. Unit tests under `tests/Unit/`.

**Target Platform**: Laravel application (12.x/13.x) consuming this package via Composer.

**Project Type**: Single Composer package (library) — no frontend/mobile component.

**Performance Goals**: N/A (config parsing is synchronous, in-process, called at most a handful of times per request); no specific throughput targets.

**Constraints**: Must not shipping a default for `plugin_key` (FR-004); must not throw at service-provider boot for missing required credentials (FR-017); must follow existing package conventions (service provider, facade, publishable config) per Constitution Principle V.

**Scale/Scope**: One config file, one value object, two exception classes, one service-provider registration/publish addition. No controllers, jobs, or HTTP clients are implemented in this feature (those are separate, dependent issues).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Check | Status |
|---|---|---|
| I. Test-First with Pest | Plan allocates Pest unit tests (language parsing, path normalization, timeout validation, missing-required-value errors) written alongside `Erecht24Settings`; Testbench exercises `vendor:publish` for the config tag. | PASS |
| II. Static Analysis Gate | New code (`Erecht24Settings`, two exception classes, provider changes) is plain typed PHP with no dynamic magic; `composer analyse` must stay green — no suppressions planned. | PASS |
| III. Consistent Code Style | `vendor/bin/pint` run before commit; no style-only diffs mixed with this feature's behavioral changes. | PASS |
| IV. Backward-Compatible API | This is net-new public surface (new config file, new class, new publish tag) on a package with no prior release of these keys — no existing contract is broken. `CHANGELOG.md` gets a MINOR entry. | PASS |
| V. Laravel Package Conventions | Config registered/published through `ERecht24ServiceProvider::register()`/`boot()` exactly as the convention requires; `Erecht24Settings` is bound into the container rather than instantiated ad hoc, so it is resolvable via the facade/container like the rest of the package. | PASS |

No violations — Complexity Tracking table is not needed.

## Project Structure

### Documentation (this feature)

```text
specs/001-erecht24-config/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md         # Phase 1 output
├── quickstart.md         # Phase 1 output
├── contracts/            # Phase 1 output (public API contract)
└── tasks.md              # Phase 2 output (/speckit.tasks — not created here)
```

### Source Code (repository root)

```text
config/
└── erecht24.php                          # New: publishable config, tag "erecht24-config"

src/
├── ERecht24ServiceProvider.php           # Modified: mergeConfigFrom + publishes() + bind Erecht24Settings
├── Facades/
│   └── ERecht24.php                      # Unchanged
├── Config/
│   └── Erecht24Settings.php              # New: typed settings value object
└── Exceptions/
    ├── InvalidConfigurationException.php # New
    └── MissingConfigurationException.php # New

tests/
├── Unit/
│   ├── ServiceProviderTest.php           # Existing — extended with config publish/bind assertions
│   └── Config/
│       └── Erecht24SettingsTest.php      # New: languages(), pushPath(), timeout(), required-value errors
└── Feature/
    └── (none added by this feature)
```

**Structure Decision**: Single-package layout (Option 1, library variant) already established by the repo (`src/`, `tests/`, PSR-4 `KaiHempel\ERecht24\`). This feature adds a `Config` and `Exceptions` namespace under `src/`, mirroring the issue's own proposed `Config\Erecht24Settings` class, and keeps config publishing inside the existing `ERecht24ServiceProvider` rather than introducing a second provider.

## Complexity Tracking

*No Constitution Check violations — section intentionally left without entries.*
