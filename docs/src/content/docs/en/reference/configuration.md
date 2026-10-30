---
title: "Configuration"
description: "Reference of all ERECHT24 environment variables and the settings that exist only in config/erecht24.php."
sidebar:
  order: 1
---

## `.env` reference

| Variable | Default | Description |
|---|---|---|
| `ERECHT24_API_KEY` | – | **Required.** API key of your eRecht24 project. |
| `ERECHT24_PLUGIN_KEY` | – | **Required.** Plugin key sent with every API request. |
| `ERECHT24_PUSH_SECRET` | – | **Required for push.** Secret issued by `erecht24:register`. Without it the push endpoint answers `503`. |
| `ERECHT24_PUSH_PATH` | `/api/erecht24/push` | Path of the push endpoint. Combined with `APP_URL` to build the push URL that is registered at eRecht24. |
| `ERECHT24_PUSH_ENABLED` | `true` | Set to `false` to not register the push route. |
| `ERECHT24_PUSH_RATE_LIMIT` | `30` | Maximum push requests per minute per IP address. Must be a positive integer. |
| `ERECHT24_AUTHOR_MAIL` | – | Optional contact email sent to eRecht24 when registering the push client. |
| `ERECHT24_IMPRINT_SEAL` | – | Optional trust seal variant for the imprint: `light-horizontal`, `light-vertical`, `dark-horizontal` or `dark-vertical`. Requires the published images, see [Trust seals](/en/usage/blade-components/#trust-seals). Unset or empty shows no seal; other values throw an `InvalidConfigurationException` when read. |
| `ERECHT24_PRIVACY_POLICY_SEAL` | – | Same as above, for the privacy policy. |
| `ERECHT24_BASE_URL` | `https://api.e-recht24.de/v2` | API base URL. |
| `ERECHT24_TEXT_LANGUAGES` | `de,en` | Comma-separated languages to sync and serve. Only `de` and `en` are supported. The first entry is the fallback language. |
| `ERECHT24_DISK` | `local` | Filesystem disk for the stored texts. |
| `ERECHT24_DIRECTORY` | `erecht24` | Directory on that disk. |
| `ERECHT24_TIMEOUT` | `10` | HTTP timeout in seconds for API requests. |
| `ERECHT24_SYNC_TRIES` | `3` | Number of attempts of the queued sync job. |

Missing credentials throw `MissingConfigurationException` when they are first used, not when the application boots. Invalid values (for example an unsupported language or a non-numeric rate limit) throw `InvalidConfigurationException`.

## Settings without an environment variable

These keys exist only in the published `config/erecht24.php`:

| Key | Default | Description |
|---|---|---|
| `queue.connection` | `null` | Queue connection for the sync job. `null` uses the application's default connection. |
| `queue.name` | `null` | Queue name for the sync job. `null` uses the connection's default queue. |
| `sync.backoff` | `[60, 300, 900]` | Seconds to wait between sync job attempts. |
