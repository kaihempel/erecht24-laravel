# Public API Contract: eRecht24 Artisan Commands

## Artisan Commands

### `erecht24:register {--push-uri=} {--write-env}`

| Aspect | Contract |
|---|---|
| Arguments | none |
| Options | `--push-uri=<uri>` (optional explicit push URI, overrides computed one); `--write-env` (write the secret into `.env` instead of only printing it) |
| Exit code | `0` on success (client created or updated); non-zero on any failure (localhost URI rejected, 3-client limit hit with no match, API error) |
| Output (success) | Client ID and the issued secret, printed once, in the form `ERECHT24_PUSH_SECRET=<secret>` (plus the client ID on its own line) |
| Output (3-client abort) | A table/list of the existing clients (ID + push URI, no secrets) and instructions to free a slot |
| Secrets | Never prints an API key, plugin key, or any secret other than the one just issued by this invocation |

### `erecht24:unregister {client-id?} {--force}`

| Aspect | Contract |
|---|---|
| Arguments | `client-id` (optional int; when omitted, resolves to the client matching the current push URI) |
| Options | `--force` (skip the confirmation prompt) |
| Exit code | `0` on successful deletion; non-zero if confirmation is declined, the client ID doesn't exist, or no client matches the current push URI when none was given |
| Confirmation | Prompted before deletion unless `--force` is given, regardless of whether `client-id` was supplied |
| Secrets | Never prints any secret |

### `erecht24:status {--test-push}`

| Aspect | Contract |
|---|---|
| Arguments | none |
| Options | `--test-push` (additionally ping the client matching the current push URI) |
| Exit code | `0` always, unless an unexpected internal error occurs — missing config or an unreachable `/clients` endpoint are reported inline, not treated as command failure |
| Output | Configuration presence (booleans only), configured languages, registered clients (ID + push URI, no secrets), stored legal texts with their last-fetched timestamps, and (with `--test-push`) the ping result or a "no matching client" notice |
| Secrets | Never prints `api_key`, `plugin_key`, `push_secret`, or any client secret — only presence (`true`/`false`) |

### `erecht24:sync {type?}` (pre-existing, unchanged by this feature)

Documented in `specs/004-legal-text-sync/contracts/public-api.md`; reused as-is.

## New Public Classes

### `KaiHempel\ERecht24\Registration\PushClientRegistrar`

```php
final class PushClientRegistrar
{
    public function __construct(Erecht24Client $client, Erecht24Settings $settings) {}

    /** @throws LocalPushUriException|TooManyPushClientsException|Erecht24ApiException|Erecht24AuthenticationException */
    public function register(?string $pushUriOverride = null): PushClient;

    /** @throws PushClientNotFoundException|Erecht24ApiException|Erecht24AuthenticationException */
    public function unregister(?int $clientId = null): PushClient;

    public function currentPushUri(?string $override = null): string;

    public function isLocalUri(string $uri): bool;
}
```

### `KaiHempel\ERecht24\Status\StatusInspector`

```php
final class StatusInspector
{
    public function __construct(Erecht24Client $client, Erecht24Settings $settings, LegalTextStore $store) {}

    public function inspect(): StatusReport;

    public function testPush(StatusReport $report, string $currentPushUri): TestPushResult;
}
```

### `KaiHempel\ERecht24\Support\EnvFileWriter`

```php
final class EnvFileWriter
{
    /** Returns false (does not throw) when the file is not writable. */
    public function write(string $envPath, string $key, string $value): bool;
}
```

### New exceptions (`KaiHempel\ERecht24\Exceptions`)

- `LocalPushUriException` — thrown by `register()` for a localhost/dev-TLD push URI.
- `TooManyPushClientsException` — thrown by `register()` when 3 clients exist with no match; carries the existing `PushClient[]`.
- `PushClientNotFoundException` — thrown by `unregister()` when no client resolves.

All extend `\RuntimeException`, matching the existing exception hierarchy under `src/Exceptions/`; none of their messages ever include a secret, API key, or plugin key.

### `Erecht24Settings` additions (additive, backward-compatible)

```php
public function pushUri(?string $override = null): string;   // config('app.url') . pushPath(), or $override
public function authorMail(): ?string;                         // ERECHT24_AUTHOR_MAIL, optional
public function hasApiKey(): bool;                              // non-throwing presence check
public function hasPluginKey(): bool;                           // non-throwing presence check
public function hasPushSecret(): bool;                          // non-throwing presence check
```

## New Config Keys (`config/erecht24.php`, additive)

```php
'author_mail' => env('ERECHT24_AUTHOR_MAIL'),
```

(`push_path` already exists from a prior feature and is reused unchanged.)
