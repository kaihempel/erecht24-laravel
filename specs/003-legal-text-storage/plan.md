# Implementation Plan: Legal Text Storage Layer

**Branch**: `003-legal-text-storage` | **Date**: 2026-10-02 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/003-legal-text-storage/spec.md`

## Summary

Add a `LegalTextStore` that persists eRecht24 legal texts as plain HTML files (never `.php`/`.blade.php`) on a configurable Laravel filesystem disk, one file per type/language (`{directory}/{type-slug}.{lang}.html`), with a companion atomically-written `meta.json` per type/language holding supplementary info (fetched-at timestamp, source modification date). Writes are atomic (temp file + rename) so a failed write never corrupts existing content and always surfaces the failure to the caller. Bound as a container singleton alongside the existing `Erecht24Settings` and `Erecht24Client`.

## Technical Context

**Language/Version**: PHP 8.3 (per `composer.json` `require.php: ^8.3`)

**Primary Dependencies**: `illuminate/support` (Laravel `Storage` filesystem abstraction), existing `Erecht24Settings` (disk/directory/languages config), existing `LegalTextType` enum

**Storage**: Laravel filesystem disk (default `local`), directory `erecht24`; one `.html` file + one `meta.json` per type/language

**Testing**: Pest 3/4 via `orchestra/testbench`, `Storage::fake()` for all storage-layer tests (per constitution Principle I and the spec's acceptance criteria)

**Target Platform**: Laravel 12/13 application (package consumed via Composer)

**Project Type**: Laravel package (library) — single `src/` + `tests/` structure, no frontend/mobile component

**Performance Goals**: N/A — synchronous local filesystem I/O for infrequent (on fetch/push) reads/writes; no throughput target beyond "no perceptible delay for a single file read/write"

**Constraints**: Atomic writes only (no partial/truncated files ever visible to readers); never produce executable PHP/Blade output; no ordering guarantee required for concurrent writes to the same type/language (last-write-wins, non-corrupting)

**Scale/Scope**: 3 legal text types × 2 supported languages (`de`, `en`) = 6 content files + 6 metadata entries per installation; single-tenant per Laravel application instance

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Check | Status |
|---|---|---|
| I. Test-First with Pest | Feature will be implemented with Pest tests under `tests/Unit/Storage/` and/or `tests/Feature/`, using `Storage::fake()`, written to cover every acceptance scenario and edge case in spec.md before/alongside implementation | PASS (planned) |
| II. Static Analysis Gate | New code (`LegalTextStore`, supporting value objects) will be typed strictly (`declare(strict_types=1)`, typed properties/returns) to pass `composer analyse` at the configured Larastan level | PASS (planned) |
| III. Consistent Code Style | New files will be formatted via `vendor/bin/pint` before commit | PASS (planned) |
| IV. Backward-Compatible Public API | `LegalTextStore` is a new public class/singleton binding — purely additive; no existing public signatures change | PASS |
| V. Laravel Package Conventions | Bound in `ERecht24ServiceProvider::register()` as a singleton alongside existing bindings; uses `config('erecht24.disk')` / `config('erecht24.directory')` already published via existing config, no parallel bootstrapping | PASS |

No violations requiring justification; Complexity Tracking section is not needed.

## Project Structure

### Documentation (this feature)

```text
specs/003-legal-text-storage/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
├── contracts/           # Phase 1 output (/speckit-plan command)
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

```text
src/
├── Config/
│   └── Erecht24Settings.php        # existing — disk()/directory()/languages() already present
├── Enums/
│   └── LegalTextType.php           # existing — fileSlug() already present
├── Exceptions/
│   ├── InvalidConfigurationException.php   # existing
│   └── LegalTextStoreException.php         # NEW — wraps underlying storage-write failures
├── Storage/
│   └── LegalTextStore.php          # NEW — put/get/has/lastModified/forget
├── DTOs/
│   └── LegalTextMetadata.php       # NEW — immutable fetchedAt / sourceModifiedAt value object
└── ERecht24ServiceProvider.php     # MODIFIED — bind LegalTextStore as singleton

tests/
├── Unit/
│   └── Storage/
│       └── LegalTextStoreTest.php  # NEW — covers all FRs/edge cases with Storage::fake()
└── Feature/
    └── (no new feature tests expected; storage is exercised at unit level with fake disk)
```

**Structure Decision**: Single Laravel package project (existing `src/`/`tests/` layout). New code lives under a new `src/Storage/` namespace for the store itself, reusing the existing `Config`, `Enums`, and `Exceptions` namespaces, matching the project's existing per-concern folder structure (`Config/`, `Enums/`, `DTOs/`, `Exceptions/`, `Facades/`).

## Complexity Tracking

*No Constitution Check violations — this section intentionally has no entries.*
