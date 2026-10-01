# Phase 0 Research: eRecht24 Package Configuration & Environment Handling

All Technical Context fields were resolvable directly from the existing repository (`composer.json`, `src/ERecht24ServiceProvider.php`, `.specify/memory/constitution.md`) and the `/speckit.clarify` session — no open `NEEDS CLARIFICATION` markers remain. This document records the decisions made and the alternatives considered.

## Decision: Config publishing mechanism

- **Decision**: Register the config via `$this->mergeConfigFrom(__DIR__.'/../config/erecht24.php', 'erecht24')` in `register()`, and publish it via `$this->publishes([__DIR__.'/../config/erecht24.php' => config_path('erecht24.php')], 'erecht24-config')` in `boot()`.
- **Rationale**: This is the standard Laravel package pattern (Constitution Principle V — "Laravel Package Conventions") and matches what `php artisan vendor:publish --tag=erecht24-config` (required by FR-003 and the issue) expects. `mergeConfigFrom` ensures sane defaults work even before the consumer publishes the file.
- **Alternatives considered**: A dedicated `Erecht24ConfigServiceProvider` — rejected because Principle V requires routing through the existing single service provider rather than adding parallel bootstrapping mechanisms, and the scope here doesn't justify a second provider.

## Decision: Settings value object shape

- **Decision**: `KaiHempel\ERecht24\Config\Erecht24Settings`, a final, constructor-injected class that reads from Laravel's `Illuminate\Contracts\Config\Repository` (not the `config()` helper directly), exposing one typed public method per concern: `apiKey(): string`, `pluginKey(): string`, `pushSecret(): string`, `pushPath(): string`, `baseUrl(): string`, `languages(): array`, `disk(): string`, `directory(): string`, `timeout(): int`, `queueConnection(): ?string`, `queueName(): ?string`.
- **Rationale**: Matches the issue's explicit ask for a `Config\Erecht24Settings` value object with typed accessors (FR-011). Injecting the config repository (rather than calling the `config()` helper internally) keeps the class unit-testable without booting a full Laravel app — it can be constructed directly with an `Illuminate\Config\Repository` instance in Pest tests, while still being resolved through the container in real usage.
- **Alternatives considered**: A plain array-based "settings bag" with magic `__get` — rejected: fails Static Analysis Gate (Principle II) since PHPStan cannot type-check dynamic property access, and obscures validation entry points.

## Decision: Validation and error strategy (languages, push_path, timeout)

- **Decision**: All parsing/validation happens lazily, inside each accessor method, not in a constructor or boot-time step. `languages()` splits on `,`, trims, lowercases, dedupes (`array_values(array_unique(...))`), and throws `InvalidConfigurationException` if any resulting entry is outside `['de', 'en']` or if the result is empty. `pushPath()` trims whitespace, strips redundant leading slashes down to one (`'/'.ltrim($path, '/')`), strips any trailing slash (unless the path is just `/`), and falls back to the documented default when the raw value is empty. `timeout()` casts via a strict numeric check (`is_numeric($raw) && (int) $raw > 0`) and throws `InvalidConfigurationException` otherwise.
- **Rationale**: Directly implements FR-012 through FR-015, FR-018, FR-019, and the clarified behavior for `timeout`. Lazy (on-access) evaluation rather than eager (constructor-time) evaluation is required by FR-017 — booting the app must not fail just because `.env` is incomplete.
- **Alternatives considered**: Eager validation of all settings in the constructor — rejected, directly conflicts with FR-005/FR-017 ("fail only at the point of use").

## Decision: Exception types

- **Decision**: Two new exception classes under `src/Exceptions/`: `InvalidConfigurationException` (malformed/unsupported values — languages, timeout) and `MissingConfigurationException` (required-but-empty credentials — `api_key`, `plugin_key`, `push_secret`), both extending PHP's `\RuntimeException` and constructed via named static factory methods (e.g. `MissingConfigurationException::forKey('api_key')`) that produce a descriptive message naming the offending config key.
- **Rationale**: FR-016/FR-018 require the two failure modes to be distinguishable; static factories centralize message wording so tests can assert on exception class alone rather than fragile string matching, satisfying SC-006 ("identify exactly which setting is missing from the error message alone").
- **Alternatives considered**: A single `Erecht24ConfigurationException` with an error-code property — rejected as a needless indirection; distinct classes are simpler to `expectException()` against in Pest and align with the issue text, which already names both exception types explicitly.

## Decision: Testing approach

- **Decision**: Pure unit tests in `tests/Unit/Config/Erecht24SettingsTest.php`, constructing `Erecht24Settings` directly against an in-memory `Illuminate\Config\Repository` seeded with various `erecht24.*` arrays (no HTTP, no queue, no Testbench app boot required for the validation logic itself). `tests/Unit/ServiceProviderTest.php` is extended with a Testbench-based assertion that `vendor:publish --tag=erecht24-config` produces the file and that the container resolves `Erecht24Settings`.
- **Rationale**: Matches Constitution Principle I (Pest, Testbench for package-boundary behavior) while keeping the validation-heavy unit tests fast and dependency-free, consistent with the "Independent Test" clauses already written into each user story in spec.md.
- **Alternatives considered**: Testing only through the facade/container — rejected as slower and less precise for the many language/path/timeout edge cases enumerated in the spec's acceptance scenarios.
