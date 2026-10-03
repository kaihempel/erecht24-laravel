# Implementation Plan: eRecht24 Artisan Commands

**Branch**: `005-artisan-commands` | **Date**: 2026-10-03 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/005-artisan-commands/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Add three new Artisan commands — `erecht24:register`, `erecht24:unregister`, `erecht24:status` — alongside the already-existing `erecht24:sync`. Registration/unregistration logic is extracted into a new `PushClientRegistrar` service (idempotent create-or-update by push URI, client-side 3-client-limit enforcement, localhost/dev-TLD URI rejection) so the command classes stay thin and testable. Health reporting is extracted into a new `StatusInspector` service producing a `StatusReport` DTO (configuration presence, registered clients, stored text timestamps, optional test-push ping) that never exposes secret values. A small `EnvFileWriter` support class handles the single-line `.env` update for `--write-env`, falling back to printing the secret when the file isn't writable. `Erecht24Settings` gains additive, non-throwing presence checks and a computed `pushUri()` helper.

## Technical Context

**Language/Version**: PHP 8.3 (per `composer.json` `"php": "^8.3"`)

**Primary Dependencies**: Laravel `illuminate/console`, `illuminate/http`, `illuminate/support` (all already declared); no new Composer dependencies

**Storage**: Files on the configured Laravel filesystem disk via the existing `LegalTextStore` (read-only use, for `status`); plain local filesystem write to the host application's `.env` for `--write-env` (new, package-root-relative `base_path('.env')`)

**Testing**: Pest v3/v4 with `pestphp/pest-plugin-laravel` and `orchestra/testbench`, per Constitution Principle I; `Http::fake()` for all eRecht24 API interactions, real temp files (`vfsStream`-free — plain `tempnam()`/temp dir fixtures) for `.env`-writing tests

**Target Platform**: Laravel application (package consumed via Composer), Laravel 12/13

**Project Type**: Single library/package (existing `src/` + `tests/` layout, no frontend)

**Performance Goals**: None beyond "command completes promptly"; `status` makes at most one `/clients` call and reads already-local stored-file metadata, no new numeric target

**Constraints**: Must never print `api_key`, `plugin_key`, or any previously issued secret under any flag combination (FR-016); `register` must never create a 4th client or a duplicate client for the same push URI; `unregister` must never delete without confirmation unless `--force` is given

**Scale/Scope**: At most 3 push clients per eRecht24 account (API-side ceiling); 3 legal text types × up to 2 configured languages for `status`'s stored-text listing

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Check | Status |
|---|---|---|
| I. Test-First with Pest | `PushClientRegistrar`, `StatusInspector`, `EnvFileWriter`, and all three new commands get Pest Feature/Unit tests under Testbench, written alongside implementation in tasks.md | PASS (planned) |
| II. Static Analysis Gate | New classes strictly typed (`declare(strict_types=1)`, typed properties/returns, `final` classes/DTOs) to pass `composer analyse` at the configured Larastan level | PASS (no planned suppressions) |
| III. Consistent Code Style | All new files run through `vendor/bin/pint` before commit; formatting not bundled with behavior changes | PASS |
| IV. Backward-Compatible Public API | Purely additive: new commands, new classes (`PushClientRegistrar`, `StatusInspector`, `StatusReport`, `LegalTextStatus`, `TestPushResult`, `EnvFileWriter`), new `Erecht24Settings` methods, new `author_mail` config key. No existing method signature, config key, or default behavior changes → MINOR version bump, `CHANGELOG.md` entry required | PASS |
| V. Laravel Package Conventions | New services bound as singletons in `ERecht24ServiceProvider`; new commands auto-registered through the existing `runningInConsole()` block; new config key published via the existing `erecht24-config` tag; no parallel bootstrapping mechanism introduced | PASS |

No violations requiring the Complexity Tracking table.

## Project Structure

### Documentation (this feature)

```text
specs/005-artisan-commands/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
│   └── public-api.md
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

```text
src/
├── Config/Erecht24Settings.php          # extend: pushUri(), authorMail(), hasApiKey()/hasPluginKey()/hasPushSecret()
├── Erecht24Client.php                   # existing, unchanged (listClients/createClient/updateClient/deleteClient/fireTestPush consumed)
├── DTOs/PushClient.php                  # existing, unchanged
├── Storage/LegalTextStore.php           # existing, unchanged (has()/lastModified() consumed for status)
├── Sync/LegalTextSynchronizer.php       # existing, unchanged (consumed by the pre-existing erecht24:sync command)
├── Registration/
│   └── PushClientRegistrar.php          # new: register(), unregister(), currentPushUri(), isLocalUri()
├── Status/
│   ├── StatusInspector.php              # new: inspect(), testPush()
│   ├── StatusReport.php                 # new: DTO
│   ├── LegalTextStatus.php              # new: DTO
│   └── TestPushResult.php               # new: DTO
├── Support/
│   └── EnvFileWriter.php                # new: write()
├── Exceptions/
│   ├── LocalPushUriException.php        # new
│   ├── TooManyPushClientsException.php  # new
│   └── PushClientNotFoundException.php  # new
├── Console/
│   ├── SyncLegalTextCommand.php         # existing, unchanged
│   ├── RegisterPushClientCommand.php    # new: erecht24:register {--push-uri=} {--write-env}
│   ├── UnregisterPushClientCommand.php  # new: erecht24:unregister {client-id?} {--force}
│   └── StatusCommand.php                # new: erecht24:status {--test-push}
└── ERecht24ServiceProvider.php          # extend: bind PushClientRegistrar/StatusInspector/EnvFileWriter, register 3 new commands

config/erecht24.php                      # extend: author_mail key

tests/
├── Unit/Config/Erecht24SettingsPushTest.php
├── Unit/Registration/PushClientRegistrarLocalUriTest.php
├── Unit/Support/EnvFileWriterTest.php
├── Feature/Registration/PushClientRegistrarRegisterTest.php
├── Feature/Registration/PushClientRegistrarUnregisterTest.php
├── Feature/Status/StatusInspectorInspectTest.php
├── Feature/Status/StatusInspectorTestPushTest.php
├── Feature/Console/RegisterPushClientCommandTest.php
├── Feature/Console/UnregisterPushClientCommandTest.php
├── Feature/Console/StatusCommandTest.php
└── Unit/SecretRedactionTest.php          # extend existing (if present) to cover new commands' output
```

**Structure Decision**: Mirrors the `004-legal-text-sync` layout — new orchestration services live in their own top-level namespaces (`Registration`, `Status`) alongside the existing `Sync` namespace, consuming but never modifying `Erecht24Client`, `Erecht24Settings`, and `LegalTextStore`. `Support/EnvFileWriter` is a small, framework-adjacent utility kept separate from the domain services so it can be unit-tested with plain temp files rather than Testbench. Commands stay thin (argument/option parsing, confirmation, output formatting only), with all branching logic (idempotent match, 3-client limit, local-URI rejection, non-throwing status assembly) in the underlying services — consistent with how `SyncLegalTextCommand` delegates to `LegalTextSynchronizer`.

## Complexity Tracking

No Constitution Check violations — table omitted.
