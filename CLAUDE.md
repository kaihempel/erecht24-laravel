# CLAUDE.md

Laravel package `kaihempel/erecht24-laravel`: integrates the eRecht24 legal-texts API (imprint, privacy policy, social-media privacy policy) into Laravel 12/13 apps. Requires PHP ^8.3. Root namespace `KaiHempel\ERecht24\` → `src/`.

## Commands

- `composer test` — Pest (Orchestra Testbench)
- `composer lint` — Pint check (`composer format` to fix)
- `composer analyse` — PHPStan/Larastan, level 8, `src/` only

CI (`.github/workflows`) runs lint + analyse and the test matrix (PHP 8.3/8.4, prefer-lowest/stable). Run all three before finishing a change.

## Architecture

- `ERecht24ServiceProvider` — merges `config/erecht24.php`, binds `Erecht24Settings`, `Erecht24Client`, `LegalTextStore` as singletons; publishes config under tag `erecht24-config`.
- `Config/Erecht24Settings` — the only place that reads `erecht24.*` config. Typed accessors; required credentials (`apiKey`, `pluginKey`, `pushSecret`) throw `MissingConfigurationException` lazily (at use, not boot); invalid values throw `InvalidConfigurationException`. Supported languages: `de`, `en`.
- `Erecht24Client` — HTTP client (Laravel `Http`) for `/imprint`, `/privacyPolicy`, `/privacyPolicySocialMedia` and `/clients` CRUD + `testPush`. Retries 3× (200ms) on connection errors, 5xx, 429. Maps failures to `Erecht24ApiException` (401 → `Erecht24AuthenticationException`). Never put keys/secrets in exception messages (covered by `SecretRedactionTest`). API details: `specs/002-erecht24-api-client/research.md`.
- `Storage/LegalTextStore` — persists `<directory>/<fileSlug>.<lang>.html` plus `.meta.json` (`fetched_at`, `source_modified_at`) on the configured disk. Writes are temp-file + move; metadata failure removes the content again. Never writes `.php`/`.blade.php`. Unsupported language → `\InvalidArgumentException` before any I/O.
- `DTOs/` — readonly `LegalText`, `LegalTextMetadata`, `PushClient`; `Enums/LegalTextType` (`fileSlug()` for storage names).

## Known gotchas

- `Facades/ERecht24` uses accessor `'erecht24'`, which the service provider does **not** bind yet — the facade is not functional until bound.
- `LegalTextStore` "atomic" on non-local disks (S3) means no truncated file at the final path, not a single atomic op.
- `Erecht24Client` reads credentials in its constructor, so resolving it without configured keys throws `MissingConfigurationException`.

## Conventions

- `declare(strict_types=1)`, `final` classes, readonly DTOs, Pint formatting (`pint.json`).
- Tests: Pest, `tests/Unit` and `tests/Feature`, base `tests/TestCase.php`. Fake HTTP with `Http::fake()` and storage with `Storage::fake()`.
- Features are developed spec-first via Spec Kit under `specs/NNN-name/` (spec, plan, tasks); one branch/PR per spec, update `CHANGELOG.md` under "Unreleased".

## Status

Done: 001 config, 002 API client, 003 legal text store. In progress: `specs/004-legal-text-sync` (spec only — planned `LegalTextSynchronizer`, queued `SyncLegalTextJob`, update event; later: push endpoint and `erecht24:sync` command). None of these exist in `src/` yet.
