# Implementation Plan: eRecht24 API Client

**Branch**: `002-erecht24-api-client` | **Date**: 2026-10-02 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/002-erecht24-api-client/spec.md`

## Summary

Add a single `Erecht24Client` class, built on a preconfigured Laravel `PendingRequest`, that is
the sole integration point with the eRecht24 legal-texts API: fetch the three legal texts on
demand, manage push-client registrations (create/update/delete/list), and fire test pushes.
Endpoints, headers, and error shapes were verified against eRecht24's real OpenAPI document
(research.md) rather than the Swagger UI's landing page alone. Errors map to
`Erecht24AuthenticationException` (401 only) or `Erecht24ApiException` (everything else, 4xx/5xx),
retrying connection errors, 5xx, and 429 with bounded backoff, and never leaking the API key,
plugin key, or push secret into any thrown message or log line.

## Technical Context

**Language/Version**: PHP 8.3 (per `composer.json` `require.php`)

**Primary Dependencies**: `illuminate/http` (HTTP client facade, `PendingRequest`), `illuminate/support`
(service container, singleton binding), existing `KaiHempel\ERecht24\Config\Erecht24Settings`
(from issue #3) for `base_url`, `timeout`, `api_key`, `plugin_key`

**Storage**: N/A (this feature does not persist anything; it is a stateless HTTP client wrapper)

**Testing**: Pest 3/4 via `pestphp/pest-plugin-laravel`, `orchestra/testbench`, `Http::fake()` for
all API interactions (per constitution Principle I — NON-NEGOTIABLE test-first with Pest)

**Target Platform**: Laravel 12/13 application (library consumed via Composer; this repo itself
only runs under Testbench for its own test suite)

**Project Type**: Single library (Laravel package) — see existing `src/` / `tests/` layout from
issue #3

**Performance Goals**: Not latency-critical; bounded retry (3 attempts, short backoff) must not
make a failing call block for more than a few seconds total

**Constraints**: No credentials/secrets in exceptions or log output (FR-014); retry MUST NOT retry
non-429 4xx responses (FR-011); each eRecht24 account is capped at 3 push clients (confirmed in
research.md), so `listClients()` needs no pagination

**Scale/Scope**: One client class, one enum, two DTOs, two exception classes, one service-provider
binding — no new public config keys (all needed settings already exist from issue #3)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Check | Status |
|---|---|---|
| I. Test-First with Pest (NON-NEGOTIABLE) | Every client method, retry path, and error mapping gets a Pest test using `Http::fake()` and Testbench, written alongside implementation; secret-redaction is asserted directly | PASS (planned) |
| II. Static Analysis Gate | New classes use strict types, explicit return types, readonly DTOs — no reason to suppress Larastan; `composer analyse` run before merge | PASS (planned) |
| III. Consistent Code Style | `vendor/bin/pint` run before commit; no mixing of formatting-only changes into behavioral commits | PASS (planned) |
| IV. Backward-Compatible Public API | This is new public surface (`Erecht24Client`, `LegalTextType`, `LegalText`, `PushClient`, two exceptions) — additive, no existing public API touched, so MINOR version bump, `CHANGELOG.md` entry required | PASS (planned) |
| V. Laravel Package Conventions | `Erecht24Client` registered as a singleton in `ERecht24ServiceProvider::register()`, resolved via the container (and/or the existing `ERecht24` facade), reusing `Erecht24Settings` rather than re-reading config/env directly | PASS (planned) |

No violations requiring justification — Complexity Tracking table is empty/omitted.

## Project Structure

### Documentation (this feature)

```text
specs/002-erecht24-api-client/
├── plan.md              # This file
├── research.md          # Phase 0 output (endpoint verification, resolved decisions)
├── data-model.md         # Phase 1 output
├── quickstart.md         # Phase 1 output
├── contracts/             # Phase 1 output (public API contract)
└── tasks.md               # Phase 2 output (/speckit.tasks — not created by this command)
```

### Source Code (repository root)

```text
src/
├── ERecht24ServiceProvider.php        # existing — add Erecht24Client singleton binding
├── Config/
│   └── Erecht24Settings.php           # existing (issue #3) — reused as-is, no changes
├── Facades/
│   └── ERecht24.php                   # existing — unchanged unless convenience methods added
├── Exceptions/
│   ├── InvalidConfigurationException.php   # existing
│   ├── MissingConfigurationException.php   # existing
│   ├── Erecht24ApiException.php            # NEW — general API failure (status + message)
│   └── Erecht24AuthenticationException.php # NEW — 401 only
├── Enums/
│   └── LegalTextType.php              # NEW — Imprint / PrivacyPolicy / PrivacyPolicySocialMedia
├── DTOs/
│   ├── LegalText.php                  # NEW — readonly: type, html per language, timestamps
│   └── PushClient.php                 # NEW — readonly: id, push method/uri, cms, cms version,
│                                        plugin name, author mail, secret
└── Erecht24Client.php                 # NEW — the integration point (this feature's core)

tests/
├── Unit/                              # existing config/provider tests, unchanged
└── Feature/
    └── Erecht24Client/
        ├── LegalTextTest.php          # NEW
        ├── PushClientLifecycleTest.php # NEW (create/update/delete/list)
        ├── TestPushTest.php            # NEW
        ├── ErrorMappingTest.php        # NEW (401 → auth exception; other 4xx/5xx → api exception)
        ├── RetryBehaviorTest.php       # NEW (connection error/5xx/429 retried; other 4xx not)
        └── SecretRedactionTest.php     # NEW (no key/secret in exception messages or logs)
```

**Structure Decision**: Single-project Laravel package layout (already established by issue #3).
No new top-level directories beyond `src/Enums/`, `src/DTOs/`, and a `tests/Feature/Erecht24Client/`
subfolder; everything else slots into the existing `src/Exceptions/` and service-provider structure.

## Complexity Tracking

*No Constitution Check violations — table intentionally omitted.*
