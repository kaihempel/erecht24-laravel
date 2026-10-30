---
title: "Exceptions"
description: "All exception classes thrown by the package: parent class, when each is thrown and how to handle it."
sidebar:
  order: 4
---

All exceptions live in the namespace `KaiHempel\ERecht24\Exceptions`. No exception message contains the API key, plugin key or push secret. Messages only contain configuration key names, HTTP status codes, API messages, file paths and redacted push URLs, so they are safe to log and report.

## Hierarchy

Only `Erecht24AuthenticationException` extends `Erecht24ApiException`. All other classes extend `\RuntimeException` directly and share no package base class, so `catch (Erecht24ApiException $e)` does **not** catch them. Catch `\RuntimeException` as a coarse fallback.

## `Erecht24ApiException`

**Extends**: `\RuntimeException` (not final, so it can be subclassed).

**Thrown when**: the eRecht24 API answers a request of `Erecht24Client` with a failed response, for example `404` for an unknown legal text or client, or `503`. Connection errors, `5xx` and `429` responses are retried three times (200 ms apart) before the exception is thrown. It is also thrown by `fireTestPush()` when the test push fails: `404` for an unknown client, `496`, `497` or `498` when delivery or response validation fails, and `503`.

**Handling**: read the public readonly properties `status` (HTTP status code), `apiMessage` (message sent by the API, or `null`) and `debug` (debug data sent by the API, or `null`). Without an API message, the exception message is `eRecht24 API request failed with status N`. The Artisan commands and `erecht24:status` catch this exception and report it, with exit code `1` for the commands that fail.

## `Erecht24AuthenticationException`

**Extends**: `Erecht24ApiException`.

**Thrown when**: the API answers with HTTP `401`. It carries the same properties as its parent.

**Handling**: check `ERECHT24_API_KEY` and `ERECHT24_PLUGIN_KEY`. Catching `Erecht24ApiException` also catches this class; catch it first if you want to treat authentication errors separately.

## `MissingConfigurationException`

**Extends**: `\RuntimeException` (final).

**Thrown when**: a required credential is read but not configured: `api_key`, `plugin_key` or `push_secret`. The check happens lazily at use, not at application boot. Resolving `Erecht24Client` also throws it when the keys are missing, because the client reads the credentials in its constructor.

**Handling**: set `ERECHT24_API_KEY`, `ERECHT24_PLUGIN_KEY` and, for push, `ERECHT24_PUSH_SECRET` (see [Configuration](/en/reference/configuration/)). The register and unregister commands and `erecht24:status` catch it and report the missing key.

## `InvalidConfigurationException`

**Extends**: `\RuntimeException` (final).

**Thrown when**: a configuration value is present but invalid: `push_enabled` (not a boolean), `push_rate_limit`, `timeout` or `sync.tries` (not a positive integer), `text_languages` (empty, or containing a language other than `de` and `en`), or `seals.imprint` / `seals.privacy_policy` (not a known seal variant). The message names the offending key and the reason.

**Handling**: correct the value in your `.env` file or `config/erecht24.php`. The register and unregister commands and `erecht24:status` catch it and report it. The Blade components and the manager resolve languages through `LegalTextResolver`, which swallows this exception and falls back instead of failing. An invalid seal setting is logged as a warning by the components, which then render the text without seal.

## `LegalTextStoreException`

**Extends**: `\RuntimeException` (final).

**Thrown when**: `LegalTextStore` cannot write a file: writing the temporary file, moving it to its final path, or writing the metadata file failed. The original error is kept as the previous exception. If only the metadata write fails, the content file is removed again. The exception propagates through a single-type sync.

**Handling**: check that the configured disk and directory exist and are writable (see `ERECHT24_DISK` and `ERECHT24_DIRECTORY`). `erecht24:sync` with a single type reports the failure and exits with `1`. A queued sync job fails and is retried according to `ERECHT24_SYNC_TRIES`. When all types are synced at once, a failure is logged as a warning and the other types are still synced.

## `LocalPushUriException`

**Extends**: `\RuntimeException` (final).

**Thrown when**: registering a push client with a push URL that is not an absolute `http` or `https` URL with a host, or that points to a local or development host which eRecht24 cannot reach. The URL in the message is redacted.

**Handling**: use a publicly reachable URL: set `APP_URL` or `ERECHT24_PUSH_PATH` correctly, use a public tunnel, or pass an explicit `--push-uri`. `erecht24:register` catches the exception, prints the message and exits with `1`.

## `TooManyPushClientsException`

**Extends**: `\RuntimeException` (final).

**Thrown when**: registering a push client while the eRecht24 project already has the maximum of three clients and none of them matches the current push URL.

**Handling**: the public readonly property `clients` holds the existing push clients (`PushClient[]`) so you can show or choose one to remove. Remove one with `erecht24:unregister` and register again. `erecht24:register` lists the clients and exits with `1`.

## `PushClientNotFoundException`

**Extends**: `\RuntimeException` (final).

**Thrown when**: unregistering a push client that does not exist: either the given client id is unknown, or no registered client matches the current push URL.

**Handling**: the public readonly property `clientId` holds the requested id, or `null` when no client matched the current push URL. Check the registered clients and the id or push URL you used. `erecht24:unregister` catches the exception, prints the message and exits with `1`.
