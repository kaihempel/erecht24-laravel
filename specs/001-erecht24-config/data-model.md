# Phase 1 Data Model: eRecht24 Package Configuration & Environment Handling

This feature has no database entities. The only "entity" is the in-memory configuration value object described in spec.md's Key Entities section. It is modeled here as a value object rather than a persisted model.

## Erecht24Settings (value object)

Resolved, typed view over the `erecht24` config namespace for the lifetime of the current request/process. Immutable from the caller's perspective — all values are derived on each accessor call from the injected config repository (no internal caching beyond what Laravel's config repository itself provides).

| Field (accessor) | Type | Source config key | Source env var | Default | Validation |
|---|---|---|---|---|---|
| `apiKey()` | `string` | `erecht24.api_key` | `ERECHT24_API_KEY` | none | Required-at-use: throws `MissingConfigurationException` if empty |
| `pluginKey()` | `string` | `erecht24.plugin_key` | `ERECHT24_PLUGIN_KEY` | none (never shipped) | Required-at-use: throws `MissingConfigurationException` if empty |
| `pushSecret()` | `string` | `erecht24.push_secret` | `ERECHT24_PUSH_SECRET` | none | Required-at-use: throws `MissingConfigurationException` if empty |
| `pushPath()` | `string` | `erecht24.push_path` | `ERECHT24_PUSH_PATH` | `/api/erecht24/push` | Normalized: exactly one leading slash, no trailing slash; empty raw value falls back to default |
| `baseUrl()` | `string` | `erecht24.base_url` | `ERECHT24_BASE_URL` | `https://api.e-recht24.de/v2` | None beyond default fallback |
| `languages()` | `array<int,string>` | `erecht24.text_languages` | `ERECHT24_TEXT_LANGUAGES` | `de,en` → `['de','en']` | Split on `,`, trim, lowercase, dedupe; throws `InvalidConfigurationException` if any entry ∉ `{de,en}` or result is empty |
| `disk()` | `string` | `erecht24.disk` | `ERECHT24_DISK` | `local` | None |
| `directory()` | `string` | `erecht24.directory` | `ERECHT24_DIRECTORY` | `erecht24` | None |
| `timeout()` | `int` | `erecht24.timeout` | `ERECHT24_TIMEOUT` | `10` | Throws `InvalidConfigurationException` if non-numeric or ≤ 0 |
| `queueConnection()` | `?string` | `erecht24.queue.connection` | n/a (array key, no direct env mapping required by spec) | `null` (Laravel default connection) | None |
| `queueName()` | `?string` | `erecht24.queue.name` | n/a | `null` (Laravel default queue) | None |

### Relationships

None — this is a standalone configuration façade with no relationships to other entities. It is consumed by (but does not depend on) future features: the API client (api_key, plugin_key, base_url, timeout), the push webhook handler (push_secret, push_path), and the text sync job (text_languages, disk, directory, queue.*).

### State / Lifecycle

Stateless per resolution: each accessor call reads the current config repository state. There is no transition between states — a value is either present-and-valid, present-and-invalid (raises `InvalidConfigurationException`), or absent-when-required (raises `MissingConfigurationException`). Laravel's own config caching (`config:cache`) governs how long a given `.env`-derived value remains in effect; this feature does not add its own caching layer.

## Exceptions (supporting types)

| Class | Namespace | Extends | Purpose |
|---|---|---|---|
| `InvalidConfigurationException` | `KaiHempel\ERecht24\Exceptions` | `\RuntimeException` | Raised when a present config value fails validation (bad language code, empty language list, non-positive/non-numeric timeout). |
| `MissingConfigurationException` | `KaiHempel\ERecht24\Exceptions` | `\RuntimeException` | Raised when a required credential (`api_key`, `plugin_key`, `push_secret`) is empty at the point of access. |

Both expose a static factory (e.g. `::forKey(string $key)` / `::invalidValue(string $key, string $reason)`) so messages are generated consistently and tests can assert on exception type plus the offending key rather than matching free-text strings.
