# Implementation Plan: Legal Text Synchronization Service

**Branch**: `004-legal-text-sync` | **Date**: 2026-10-03 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/004-legal-text-sync/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Add a `LegalTextSynchronizer` service that fetches a legal text type from `Erecht24Client::legalText()` and writes it per configured language via `LegalTextStore::put()`, skipping (and reporting) languages with empty API content rather than overwriting existing files. Wrap it in a `SyncLegalTextJob` (`ShouldQueue`, `ShouldBeUnique` per type, 3 tries, exponential backoff 1m/5m/15m) dispatched from the push-handling path so the HTTP response stays fast, and reuse the same synchronizer from a new `erecht24:sync {type?}` Artisan command for manual/full resynchronization. A `LegalTextUpdated` event is dispatched only after at least one language was successfully written.

## Technical Context

**Language/Version**: PHP 8.3 (per `composer.json` `"php": "^8.3"`)

**Primary Dependencies**: Laravel `illuminate/support`, `illuminate/console`, `illuminate/queue` (queue/job infra not yet a declared dependency — see research.md), `erecht24/rechtstexte-sdk` (via existing `Erecht24Client`)

**Storage**: Files on a configurable Laravel filesystem disk, via the existing `LegalTextStore` (no new storage mechanism)

**Testing**: Pest v3/v4 with `pestphp/pest-plugin-laravel` and `orchestra/testbench`, per Constitution Principle I

**Target Platform**: Laravel application (package consumed via Composer), Laravel 12/13

**Project Type**: Single library/package (existing `src/` + `tests/` layout, no frontend)

**Performance Goals**: Push endpoint HTTP response unaffected by legal-text-provider latency (work deferred to queue) — no new numeric target beyond "does not block the request"

**Constraints**: Must not overwrite existing stored content on empty API response or job failure; job retries must not duplicate work for the same type while one is already queued/running

**Scale/Scope**: 3 legal text types × up to 2 configured languages (`de`, `en`) per `Erecht24Settings::languages()`; one job per sync trigger

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Check | Status |
|---|---|---|
| I. Test-First with Pest | New `LegalTextSynchronizer`, `SyncLegalTextJob`, `erecht24:sync` command, and `LegalTextUpdated` event each get Pest Feature/Unit tests under Testbench before/alongside implementation | PASS (planned in tasks) |
| II. Static Analysis Gate | New classes typed strictly (`declare(strict_types=1)`, typed properties/returns) to pass `composer analyse` at configured Larastan level | PASS (no planned suppressions) |
| III. Consistent Code Style | All new files run through `vendor/bin/pint` before commit; no formatting bundled with behavior changes | PASS |
| IV. Backward-Compatible Public API | Adds new public classes (`LegalTextSynchronizer`, `SyncResult`, `SyncLegalTextJob`, `LegalTextUpdated`, `erecht24:sync` command) and new config keys under `erecht24.sync.*` — purely additive, no existing signature/config/default changed → MINOR version bump, `CHANGELOG.md` entry required | PASS |
| V. Laravel Package Conventions | New service bound as singleton in `ERecht24ServiceProvider`; job/retry/backoff tunables published via existing `config/erecht24.php` rather than hardcoded; command auto-registered through the service provider, consistent with existing `publishes()` pattern | PASS |

No violations requiring the Complexity Tracking table.

## Project Structure

### Documentation (this feature)

```text
specs/[###-feature]/
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
├── Config/Erecht24Settings.php          # extend: retryTimes(), backoffSeconds(), queueConnection()/queueName() reused
├── DTOs/LegalText.php                   # existing, unchanged
├── Enums/LegalTextType.php              # existing, unchanged
├── Erecht24Client.php                   # existing, unchanged (consumed, not modified)
├── Storage/LegalTextStore.php           # existing, unchanged (consumed, not modified)
├── Sync/
│   ├── LegalTextSynchronizer.php        # new: sync(), syncAll()
│   └── SyncResult.php                   # new: DTO (type, written[], skipped[])
├── Jobs/
│   └── SyncLegalTextJob.php             # new: ShouldQueue, ShouldBeUnique, tries=3, backoff
├── Events/
│   └── LegalTextUpdated.php             # new: (type, languages)
├── Console/
│   └── SyncLegalTextCommand.php         # new: erecht24:sync {type?}
└── ERecht24ServiceProvider.php          # extend: bind LegalTextSynchronizer, register command

config/erecht24.php                      # extend: sync.tries, sync.backoff keys

tests/
├── Unit/Config/Erecht24SettingsSyncTest.php
├── Feature/Sync/LegalTextSynchronizerSyncTest.php
├── Feature/Sync/LegalTextSynchronizerFailureTest.php
├── Feature/Sync/LegalTextSynchronizerSyncAllTest.php
├── Feature/Jobs/SyncLegalTextJobUniquenessTest.php
├── Feature/Jobs/SyncLegalTextJobHandleTest.php
├── Feature/Jobs/SyncLegalTextJobRetryConfigTest.php
├── Feature/Jobs/SyncLegalTextJobFailedTest.php
├── Feature/Jobs/SyncLegalTextJobDispatchForPushTypeTest.php
├── Feature/Console/SyncLegalTextCommandAllTest.php
├── Feature/Console/SyncLegalTextCommandSingleTypeTest.php
├── Feature/Console/SyncLegalTextCommandInvalidTypeTest.php
└── Feature/Events/LegalTextUpdatedDispatchTest.php
```

**Structure Decision**: Single-package layout (existing `src/`/`tests/` PSR-4 structure under `KaiHempel\ERecht24\`). New code lives in two new namespaces, `Sync` (service + result DTO) and `Jobs`/`Events`/`Console` (mirroring Laravel's conventional top-level folders), rather than nesting everything under `Storage` or `Erecht24Client` — keeps the synchronizer as an orchestration layer that depends on, but does not modify, the existing client/store building blocks from #4/#5.

## Complexity Tracking

No Constitution Check violations — table omitted.
