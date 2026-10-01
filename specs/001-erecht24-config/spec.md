# Feature Specification: eRecht24 Package Configuration & Environment Handling

**Feature Branch**: `001-erecht24-config`

**Created**: 2026-10-01

**Status**: Draft

**Input**: User description: "Add configuration and .env handling for the eRecht24 Laravel package (GitHub Issue #3). All behavior is controlled through config/erecht24.php backed by .env variables. The push endpoint path must be configurable (default /api/erecht24/push). The languages setting must accept de, en or de,en. No plugin key may be shipped as a default, because the key identifies the plugin author's registration with eRecht24."

## Clarifications

### Session 2026-10-01

- Q: Is `push_secret` a required setting (like `api_key`/`plugin_key`) that must raise a MissingConfigurationException-style error when accessed while empty, since it's used to verify incoming webhook pushes? → A: Required when accessed — push_secret follows the same missing-required-setting error behavior as api_key/plugin_key.
- Q: Should the `timeout` setting be validated (positive integer only), raising a configuration error for non-numeric or non-positive values, or left unvalidated and simply cast/passed through as-is? → A: Validate as positive integer — non-numeric or non-positive values raise an InvalidConfigurationException.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Publish and configure the package via .env (Priority: P1)

As a Laravel application developer installing the eRecht24 package, I want to publish a configuration file and set my credentials and preferences through environment variables, so that I can control the package's behavior without editing vendor code.

**Why this priority**: Without a working, publishable configuration file, no other package feature (API calls, text sync, push webhook) can be set up at all. This is the foundation every other capability depends on.

**Independent Test**: Can be fully tested by running the package's `vendor:publish` command with the `erecht24-config` tag, confirming `config/erecht24.php` appears in the host application, and verifying each `.env` variable correctly overrides its corresponding config value.

**Acceptance Scenarios**:

1. **Given** a fresh Laravel application with the package installed, **When** the developer runs `php artisan vendor:publish --tag=erecht24-config`, **Then** a `config/erecht24.php` file is created in the application with all documented keys and their default values.
2. **Given** a published config file, **When** the developer sets `ERECHT24_API_KEY`, `ERECHT24_PLUGIN_KEY`, `ERECHT24_PUSH_SECRET`, `ERECHT24_PUSH_PATH`, `ERECHT24_BASE_URL`, `ERECHT24_TEXT_LANGUAGES`, `ERECHT24_DISK`, `ERECHT24_DIRECTORY`, and `ERECHT24_TIMEOUT` in `.env`, **Then** the application resolves each corresponding configuration value from those environment variables.
3. **Given** no `.env` overrides are set, **When** the configuration is read, **Then** `push_path` defaults to `/api/erecht24/push`, `base_url` defaults to `https://api.e-recht24.de/v2`, `text_languages` defaults to `de,en`, `disk` defaults to `local`, `directory` defaults to `erecht24`, and `timeout` defaults to `10`.
4. **Given** the shipped default configuration, **When** inspecting the `plugin_key` setting, **Then** no default value is present (it is null/empty until the developer supplies one).

---

### User Story 2 - Reliable, typed access to settings in application code (Priority: P2)

As a developer of the package (or a contributor building on top of it), I want a single typed settings object to read configuration from, so that the rest of the codebase doesn't need to know about raw config array keys, parsing rules, or `.env` naming.

**Why this priority**: Once configuration exists, internal package code (API clients, jobs, controllers) needs a dependable, typed way to consume it. This unlocks all subsequent features that read settings, but is secondary to the raw configuration existing in the first place.

**Independent Test**: Can be fully tested in isolation by instantiating the settings object against various config arrays/env combinations and asserting the typed accessor methods return expected values, independent of any HTTP or queue behavior.

**Acceptance Scenarios**:

1. **Given** a settings object backed by valid configuration, **When** any typed accessor (e.g. for `api_key`, `push_path`, `languages`, `timeout`) is called, **Then** it returns a value of the expected type (string, int, array) without the caller needing to parse raw config.
2. **Given** the settings object, **When** `languages()` is called with `ERECHT24_TEXT_LANGUAGES` unset, **Then** it returns `['de', 'en']`.
3. **Given** `ERECHT24_TEXT_LANGUAGES` set to a single valid language (`de` or `en`), **When** `languages()` is called, **Then** it returns an array containing only that language.
4. **Given** `ERECHT24_TEXT_LANGUAGES` set with mixed case, extra whitespace, or duplicate values (e.g. `" DE , en, en"`), **When** `languages()` is called, **Then** it returns a normalized, deduplicated, lowercase array (e.g. `['de', 'en']`).
5. **Given** `ERECHT24_TEXT_LANGUAGES` set to an unsupported language code (e.g. `fr`), a mix of supported and unsupported codes (e.g. `de,fr`), or an empty string, **When** `languages()` is called, **Then** a configuration error is raised identifying the invalid value.
6. **Given** `ERECHT24_PUSH_PATH` set without a leading slash, with multiple leading slashes, or with a trailing slash, **When** the push path accessor is called, **Then** it returns a normalized path with exactly one leading slash and no trailing slash.
7. **Given** `ERECHT24_TIMEOUT` set to a non-numeric or non-positive value (e.g. `abc`, `0`, `-5`), **When** the timeout accessor is called, **Then** a configuration error is raised identifying `timeout` as invalid; a positive integer value is returned unchanged otherwise.

---

### User Story 3 - Fail fast and clearly on missing required credentials (Priority: P3)

As a developer who forgot to set a required credential, I want a clear, specific error only when that credential is actually needed, so that I can diagnose and fix the problem quickly without the whole application failing to boot.

**Why this priority**: This is a safety/usability refinement on top of the settings object — it improves error experience but isn't required for the "happy path" of a fully-configured installation to work, so it's lowest priority among the three stories while still being required for release.

**Independent Test**: Can be fully tested by constructing the settings object with required values left empty and asserting that accessing those specific values throws a distinct, descriptive error, while unrelated settings and application boot remain unaffected.

**Acceptance Scenarios**:

1. **Given** `ERECHT24_API_KEY` is empty or unset, **When** the application boots without using that value, **Then** no error occurs.
2. **Given** `ERECHT24_API_KEY` is empty or unset, **When** code attempts to read the API key value, **Then** a descriptive error is raised identifying `api_key` as the missing required setting.
3. **Given** `ERECHT24_PLUGIN_KEY` is empty or unset, **When** code attempts to read the plugin key value, **Then** a descriptive error is raised identifying `plugin_key` as the missing required setting.
4. **Given** `ERECHT24_PUSH_SECRET` is empty or unset, **When** code attempts to read the push secret value (e.g. to verify an incoming webhook push), **Then** a descriptive error is raised identifying `push_secret` as the missing required setting, rather than silently skipping verification.

---

### Edge Cases

- What happens when `ERECHT24_TEXT_LANGUAGES` is set to an empty string? → Treated as invalid and must raise a configuration error (not silently fall back to defaults), per acceptance scenario in User Story 2.
- What happens when `ERECHT24_TEXT_LANGUAGES` contains the same language twice (e.g. `de,de`)? → Deduplicated to a single entry.
- What happens when `ERECHT24_PUSH_PATH` is set to just `/` or is empty? → Must still normalize to a single leading slash with no trailing slash where possible; an empty value falls back to the documented default.
- How does the system handle a `.env` value with surrounding whitespace (e.g. `" de, en "`)? → Whitespace is trimmed before validation/normalization.
- How does the system behave if `ERECHT24_TIMEOUT` is set to a non-numeric or non-positive value (e.g. `0`, `-5`)? → Treated as invalid configuration and raises an error, rather than being silently coerced to zero or passed through.
- What happens if both `api_key` and `plugin_key` are missing at once and both are accessed? → Each raises its own descriptive error at the point it is individually accessed; the system does not need to aggregate multiple missing-value errors into one.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The package MUST provide a configuration file containing the following keys: `api_key`, `plugin_key`, `push_secret`, `push_path`, `base_url`, `text_languages`, `disk`, `directory`, `timeout`, and queue settings (`queue.connection`, `queue.name`) for the sync job.
- **FR-002**: Each configuration key MUST be overridable via a corresponding environment variable: `ERECHT24_API_KEY`, `ERECHT24_PLUGIN_KEY`, `ERECHT24_PUSH_SECRET`, `ERECHT24_PUSH_PATH`, `ERECHT24_BASE_URL`, `ERECHT24_TEXT_LANGUAGES`, `ERECHT24_DISK`, `ERECHT24_DIRECTORY`, `ERECHT24_TIMEOUT`.
- **FR-003**: The configuration file MUST be publishable into the host application independently of other package assets, under a distinct publish tag.
- **FR-004**: The configuration file MUST NOT ship any default value for `plugin_key`; it must be empty/null until explicitly set, since this key identifies the plugin author's registration with eRecht24.
- **FR-005**: The `push_path` setting MUST default to `/api/erecht24/push` when not overridden.
- **FR-006**: The `base_url` setting MUST default to `https://api.e-recht24.de/v2` when not overridden.
- **FR-007**: The `text_languages` setting MUST default to `de,en` when not overridden.
- **FR-008**: The `disk` setting MUST default to `local` when not overridden.
- **FR-009**: The `directory` setting MUST default to `erecht24` when not overridden.
- **FR-010**: The `timeout` setting MUST default to `10` (seconds) when not overridden.
- **FR-011**: The system MUST provide a single typed settings accessor layer so that calling code reads configuration values through typed methods rather than directly reading raw config arrays.
- **FR-012**: The settings layer MUST expose a method that resolves the configured languages into a normalized list: values are split on commas, trimmed of whitespace, lowercased, and deduplicated.
- **FR-013**: The settings layer MUST restrict resolved languages to only `de` and `en`; any other language code present anywhere in the value MUST cause a configuration error to be raised.
- **FR-014**: The settings layer MUST raise a configuration error when the resolved language list would otherwise be empty (e.g. the raw value is an empty string).
- **FR-015**: The settings layer MUST normalize the `push_path` value to always have exactly one leading slash and no trailing slash, regardless of how it was supplied.
- **FR-016**: The settings layer MUST raise a distinct, descriptive error when a required setting (`api_key`, `plugin_key`, or `push_secret`) is accessed while empty, naming which setting is missing.
- **FR-017**: Missing required settings (`api_key`, `plugin_key`, `push_secret`) MUST NOT prevent the application from starting; the error MUST only occur at the point the specific value is used.
- **FR-018**: Invalid configuration values (unsupported language codes, empty language list, non-numeric or non-positive `timeout`) MUST be detected and reported with an error distinguishable from the "missing required value" error, identifying the invalid value and the setting it came from.
- **FR-019**: The settings layer MUST raise a configuration error when `timeout` resolves to a non-numeric or non-positive value; otherwise it MUST return the value as a positive integer.

### Key Entities

- **Package Settings**: The resolved, typed representation of the package's configuration for a given request/process. Attributes include API credentials (api key, plugin key, push secret), network settings (base URL, push path, timeout), content settings (supported text languages, storage disk, storage directory), and queue routing (connection, queue name) for background sync work. Read-only from the perspective of application code — populated from configuration/environment at the point of use.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A developer can go from installing the package to having a fully configured, working set of credentials in under 5 minutes, using only `.env` edits and the publish command — no need to read package source code.
- **SC-002**: 100% of documented configuration keys are overridable via their documented environment variable, verified by automated tests.
- **SC-003**: 100% of the documented valid language inputs (`de`, `en`, `de,en`, and whitespace/case variants thereof) resolve to the correct normalized language list, verified by automated tests.
- **SC-004**: 100% of documented invalid language inputs (unsupported codes, empty string) are rejected with a clear, actionable error message, verified by automated tests.
- **SC-005**: Zero application boot failures occur due to missing `api_key` or `plugin_key` when those values are not yet used by the current request/operation.
- **SC-006**: A developer who misconfigures a required credential can identify exactly which setting is missing from the error message alone, without inspecting package internals.

## Assumptions

- The package already has an installable structure with a Laravel service provider where config publishing and bindings are registered (depends on GitHub Issue #1).
- "de" and "en" are the only supported text languages for the foreseeable scope of this feature; adding further languages is out of scope here and would be a separate change.
- `timeout` is expressed in seconds and consumed by whatever HTTP client the package uses internally; its exact consumption mechanism is outside this spec's scope.
- The `disk`, `directory`, and queue settings do not require the same validation/normalization rigor as `text_languages` and `push_path`, since the issue does not call out specific validation rules for them beyond basic env-to-config mapping. `push_secret` is treated as a required credential (see Clarifications) rather than a loosely-validated setting.
- Configuration values are process-local (standard Laravel config caching semantics apply); no runtime/per-tenant reconfiguration is in scope.
- "Configuration error" and "missing required setting error" are two distinct, user-visible error conditions, but this spec does not mandate specific error class names or message wording — only that they are distinguishable and descriptive.
