# Data Model: eRecht24 Artisan Commands

## PushClient (existing DTO, reused — `src/DTOs/PushClient.php`)

No changes. `register`/`unregister`/`status` all consume it as-is via `Erecht24Client::listClients()`, `createClient()`, `updateClient()`, `deleteClient()`.

## TooManyPushClientsException (new exception)

Thrown by `PushClientRegistrar::register()` when 3 clients already exist and none match the target push URI (FR-004).

| Field | Type | Notes |
|---|---|---|
| `clients` | `PushClient[]` | The existing clients, for the command to list in its abort message |

## LocalPushUriException (new exception)

Thrown by `PushClientRegistrar::register()` when the computed/given push URI resolves to a localhost/loopback/dev-TLD host (FR-002).

| Field | Type | Notes |
|---|---|---|
| `uri` | `string` | The rejected URI, for the error message |

## PushClientNotFoundException (new exception)

Thrown by `PushClientRegistrar::unregister()` when an explicit client ID doesn't exist, or when no client matches the current push URI and none was given.

| Field | Type | Notes |
|---|---|---|
| `clientId` | `int\|null` | The ID that was looked up, or null if lookup was by push URI |

## PushClientRegistrar (new service — `src/Registration/PushClientRegistrar.php`)

Not a data entity but documented for its behavior-relevant contract:

| Method | Returns | Notes |
|---|---|---|
| `register(?string $pushUriOverride): PushClient` | The created/updated client, including the freshly issued `secret` | Throws `LocalPushUriException`, `TooManyPushClientsException`, `Erecht24ApiException`/`Erecht24AuthenticationException` |
| `unregister(?int $clientId): PushClient` | The deleted client (pre-deletion snapshot) | Resolves by ID when given, else by matching push URI; throws `PushClientNotFoundException` if neither resolves |
| `currentPushUri(?string $override): string` | The computed or given push URI | `$override` wins when non-null; otherwise `rtrim(config('app.url'), '/') . $settings->pushPath()` |
| `findMatchingClient(array $clients, string $pushUri): ?PushClient` | The client whose `pushUri` equals `$pushUri`, or null | Pure helper, no I/O |
| `isLocalUri(string $uri): bool` | Whether the URI's host is localhost/loopback/dev-TLD | Pure helper per research.md; no DNS resolution |

State/behavior notes:
- `register()` always calls `listClients()` first; creates only when no match found and fewer than 3 exist; updates in place when a match is found regardless of count.
- `register()` never persists anything to `.env` itself — the command layer does that via `EnvFileWriter`, keeping the registrar framework-agnostic of "where the secret is stored".

## EnvFileWriter (new support class — `src/Support/EnvFileWriter.php`)

Not a data entity but documented for its contract:

| Method | Returns | Notes |
|---|---|---|
| `write(string $envPath, string $key, string $value): bool` | `true` if written, `false` if the file is not writable | Replaces an existing `KEY=...` line in place, or appends one if absent; writes via temp-file-then-rename per research.md |

## StatusReport (new DTO — `src/Status/StatusReport.php`)

Represents the full output of `status`.

| Field | Type | Notes |
|---|---|---|
| `configuration` | `array<string, bool>` | Keys: `api_key`, `plugin_key`, `push_secret` — presence only, never values |
| `languages` | `array<int, string>` | From `Erecht24Settings::languages()`; empty if misconfigured (caught, not thrown) |
| `clients` | `PushClient[]` | From `listClients()`; empty if the API call failed (failure noted separately, not fatal to the rest of the report) |
| `texts` | `LegalTextStatus[]` | One entry per configured legal text type × language |
| `clientsError` | `string\|null` | Non-sensitive message if listing clients failed; null on success |

## LegalTextStatus (new DTO — `src/Status/LegalTextStatus.php`)

| Field | Type | Notes |
|---|---|---|
| `type` | `LegalTextType` | |
| `language` | `string` | |
| `stored` | `bool` | From `LegalTextStore::has()` |
| `fetchedAt` | `CarbonImmutable\|null` | From `LegalTextStore::lastModified()`; null if never stored |

## TestPushResult (new DTO — `src/Status/TestPushResult.php`)

Represents the outcome of `status --test-push` (FR-013).

| Field | Type | Notes |
|---|---|---|
| `matched` | `bool` | Whether a registered client's push URI matched the current one |
| `clientId` | `int\|null` | The matched client's ID, or null if `matched` is false |
| `success` | `bool` | Whether `fireTestPush()` completed without throwing; always false if `matched` is false |
| `errorMessage` | `string\|null` | Non-sensitive failure reason when `success` is false and `matched` is true |

## StatusInspector (new service — `src/Status/StatusInspector.php`)

| Method | Returns | Notes |
|---|---|---|
| `inspect(): StatusReport` | The full report | Never throws for recoverable states (missing config, unreachable clients API) — captures them into the report's fields instead, per FR-011/edge case "status still reports whatever else it can" |
| `testPush(StatusReport $report, string $currentPushUri): TestPushResult` | The ping outcome | Looks up a matching client from `$report->clients`; calls `Erecht24Client::fireTestPush()` only when matched |

## Relationships to existing entities (unchanged)

- **Erecht24Client** (`src/Erecht24Client.php`) — consumed as-is (`listClients`, `createClient`, `updateClient`, `deleteClient`, `fireTestPush`); no signature changes.
- **Erecht24Settings** (`src/Config/Erecht24Settings.php`) — extended additively with `pushUri(?string $override): string`, `authorMail(): ?string`, `hasApiKey(): bool`, `hasPluginKey(): bool`, `hasPushSecret(): bool`. Existing accessors (`pushPath()`, `apiKey()`, `pluginKey()`, `pushSecret()`, `languages()`) reused unchanged.
- **LegalTextStore** (`src/Storage/LegalTextStore.php`) — consumed as-is via `has()`/`lastModified()` for the `status` report; no modification.
- **LegalTextSynchronizer** / **SyncResult** (`src/Sync/*`) — consumed as-is by the already-existing `erecht24:sync` command; unchanged by this feature.
