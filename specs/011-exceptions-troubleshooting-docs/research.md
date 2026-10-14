# Research: Exceptions Reference and Troubleshooting Pages

All facts verified against `src/` on 2026-10-14. No open NEEDS CLARIFICATION.

## Exception facts

| Class | Declared as | Thrown from | Caught/handled by package |
|---|---|---|---|
| `Erecht24ApiException` | non-final, `extends RuntimeException`; public readonly `status`, `apiMessage`, `debug`; message = API `message` or `eRecht24 API request failed with status N` | `Erecht24Client::toException()` for every failed response of the client methods (after 3 retries on connection errors, 5xx, 429), including `fireTestPush` (404 unknown client, 496/497/498 delivery failures, 503) | `RegisterPushClientCommand`, `UnregisterPushClientCommand`, `StatusInspector` (exit 1 / reported) |
| `Erecht24AuthenticationException` | non-final, `extends Erecht24ApiException`; HTTP 401 | `Erecht24Client::toException()` when status is 401 | caught as `Erecht24ApiException` |
| `MissingConfigurationException` | final, `extends \RuntimeException`; `forKey()` | `Erecht24Settings::requireString()` for `api_key`, `plugin_key`, `push_secret`; lazily at use; also when resolving `Erecht24Client` (constructor reads credentials) | register/unregister commands, `StatusInspector` |
| `InvalidConfigurationException` | final, `extends \RuntimeException`; `forKey()` | `Erecht24Settings`: `push_enabled`, `push_rate_limit`, `text_languages` (empty / unsupported language), `timeout`, `sync.tries` | register/unregister commands, `StatusInspector`; `LegalTextResolver` swallows it and falls back |
| `LegalTextStoreException` | final, `extends \RuntimeException`; `forPath()` keeps `previous` | `LegalTextStore` private write helper (temp write, move, metadata write failed) → propagates through `LegalTextSynchronizer` | `SyncLegalTextCommand` (exit 1); queued `SyncLegalTextJob` fails and is retried per `sync.tries` |
| `LocalPushUriException` | final, `extends \RuntimeException`; `forInvalidUri()`, `forUri()` (URI redacted via `PushUri::redact`) | `PushClientRegistrar::register()`: URI not absolute http(s) with host, or local/dev host | `RegisterPushClientCommand` (exit 1) |
| `TooManyPushClientsException` | final, `extends \RuntimeException`; public readonly `clients` (`PushClient[]`) | `PushClientRegistrar::register()` when the project already has the maximum clients and none matches the push URI | `RegisterPushClientCommand` lists clients, exit 1 |
| `PushClientNotFoundException` | final, `extends \RuntimeException`; public readonly `clientId` (`?int`) | `PushClientRegistrar::unregister()`: unknown id (`forId`) or no client for the current push URI (`forCurrentPushUri`, `clientId` null) | `UnregisterPushClientCommand` (exit 1) |

Hierarchy consequence: only `Erecht24AuthenticationException` extends `Erecht24ApiException`; the other six do **not** share a package base class, so `catch (Erecht24ApiException)` does not cover them. Issue text says "extends RuntimeException" for the API exception; the five final classes also extend `\RuntimeException` directly.

Secret handling: messages contain config key names, status codes, API messages, paths and redacted push URIs, never key/secret values (covered by `SecretRedactionTest`).

## Decisions

- **Decision**: Document the observed hierarchy exactly (see above) and tell readers to catch `\RuntimeException` as a coarse fallback. **Rationale**: Accuracy is the acceptance criterion. **Alternatives**: Invent a common base class – rejected, would be an API change.
- **Decision**: Troubleshooting uses H2 per entry with three bold labels. **Rationale**: Script-checkable, sidebar/TOC friendly, anchors usable from the deployment page. **Alternatives**: Tables – poor for multi-line fixes; Starlight tabs – overkill.
- **Decision**: Add a ninth entry for "eRecht24 cannot reach the push URL". **Rationale**: Keeps the former "Public HTTPS" pitfall covered (FR-008). **Alternatives**: keep it in deployment – contradicts "shorten to checklist".
- **Decision**: Remove only `reference/exceptions` and `project/troubleshooting` from the script's reserved set. **Rationale**: These become real pages; `project/changelog` stays a placeholder.
