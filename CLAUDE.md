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
- `View/` — Blade integration under view namespace `erecht24` (provider `loadViewsFrom` + `Blade::componentNamespace`): `LegalText` (`<x-erecht24::legal-text type=…>`), `Imprint`, `PrivacyPolicy`, `PrivacyPolicySocialMedia` extend `AbstractLegalTextComponent`; `LegalTextResolver` (singleton) picks lang (explicit → app locale primary subtag → first configured → any stored, never throws, logs read errors) and returns `ResolvedLegalText` or null → `erecht24::missing`. Views in `resources/views` publish via tag `erecht24-views`. Content is output unescaped (trusted API HTML). Components read the store only, no HTTP. Views are called via `View::first([...])` because Larastan's `view-string` cannot see package-namespaced views.
- `Erecht24Manager` (singleton) + `Facades/ERecht24` — programmatic access for Inertia etc.: `html()`, `has()`, `resolve()` (returns public `View\ResolvedLegalText` with `content`, `lang`, `requestedLang`, `isFallback()`), `lastModified()`, `languages()`; accepts `LegalTextType` or string (unknown → `\InvalidArgumentException`); delegates language fallback to `LegalTextResolver`; store only, no HTTP.
- `Http/Controllers/PushController` — invokable push webhook, registered in provider `boot()` as `POST pushPath()` named `erecht24.push` (no `web` group, `throttle:erecht24-push` via `ThrottleRequests::using`, limiter `pushRateLimit()`/min/IP) unless `pushEnabled()` is false or routes are cached. Order: no secret → 503, `hash_equals` secret fail → 403, `ping` → pong, text type → `SyncLegalTextJob::dispatch` (`queued`), else 422. Never logs secret/payload. Boot-time config in tests: `rebootWithConfig()` in `tests/TestCase.php`.
- `DTOs/` — readonly `LegalText`, `LegalTextMetadata`, `PushClient`; `Enums/LegalTextType` (`fileSlug()` for storage names).

## Documentation

- Docs site lives in `docs/` (Astro Starlight, standalone Node project); content in `docs/src/content/docs/{en,de}/`.
- Requires Node 22.12+. Preview: `cd docs && npm run dev`; build: `cd docs && npm run build`.
- EN and DE pages must stay content-equivalent (same file names in both locales).
- `npm run check` (part of build) runs `scripts/check-parity.mjs` (EN/DE stems, `.md`≙`.mdx`) and `scripts/check-content.mjs` (EN + DE page contract: frontmatter, no H1, `/<locale>/` links and anchors resolve, all `ERECHT24_*` vars in configuration page; DE also matches EN heading/aside/Card/TabItem/Steps counts; also enforces the `reference/exceptions` contract (one H2 per class in `src/Exceptions/` with Extends/Thrown when/Handling labels), the `project/troubleshooting` contract (≥9 entries with Symptom/Cause/Fix labels) and the deployment → troubleshooting link; `project/changelog` is reserved (import-only page rendering the root `CHANGELOG.md`, which has no H1 so the page keeps a single title)).
- CI: `.github/workflows/docs.yml` builds the docs on PRs and pushes; pushes to `main` also deploy `docs/dist` to GitHub Pages (custom domain `erecht24-laravel.kuweh.de` via `docs/public/CNAME`). `docs/` is `export-ignore`d from the dist archive.

## Known gotchas

- `LegalTextStore` "atomic" on non-local disks (S3) means no truncated file at the final path, not a single atomic op.
- `Erecht24Client` reads credentials in its constructor, so resolving it without configured keys throws `MissingConfigurationException`.

## Conventions

- `declare(strict_types=1)`, `final` classes, readonly DTOs, Pint formatting (`pint.json`).
- Tests: Pest, `tests/Unit` and `tests/Feature`, base `tests/TestCase.php`. Fake HTTP with `Http::fake()` and storage with `Storage::fake()`.
- Features are developed spec-first via Spec Kit under `specs/NNN-name/` (spec, plan, tasks); one branch/PR per spec, update `CHANGELOG.md` under "Unreleased".

## Status

Done: 001 config, 002 API client, 003 legal text store, 004 legal text sync, 005 Artisan commands, 006 Blade views/components (`src/View`, `resources/views`), README EN/DE + deployment guide (#12; env table lives on the docs configuration pages, guarded by `DocsEnvReferenceTest`; EN/DE docs pages must stay equivalent), slim READMEs linking to the docs site (#40; `README.md`/`README.de.md` stay short and content-equivalent), 007 manager resolve() / resolved language (#27), 008 Starlight docs scaffold (`docs/`, bilingual EN/DE, CI build), 009 English README moved into Starlight pages, 010 German docs translation (all DE pages except reserved placeholders), 011 exceptions reference + troubleshooting pages (EN/DE).
