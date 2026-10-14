---
title: "Troubleshooting"
description: "Symptoms, causes and fixes for common problems with the push endpoint, registration, queue and stored legal texts."
sidebar:
  order: 2
---

Find your symptom, check the cause and apply the fix. For exception classes and their handling see the [exceptions reference](/en/reference/exceptions/).

## Every push returns 403

**Symptom:** Your endpoint answers eRecht24's pushes with `403`, or `php artisan erecht24:status --test-push` fails (a rejected secret typically shows up as a `49x` status from eRecht24).

**Cause:** The secret sent by eRecht24 does not match `ERECHT24_PUSH_SECRET`. Usually the configuration is cached and still holds an old secret: with a cached configuration, changes to `.env` (including the secret written by `erecht24:register --write-env`) take effect only after the cache is rebuilt. Another cause is that the secret was re-issued, because every update of an existing client through `erecht24:register` issues a new secret, and the new value was not copied to this environment.

**Fix:** Set the current secret in `.env`, then run `php artisan config:cache`. Verify with `php artisan erecht24:status --test-push`. If unsure which secret is current, run `php artisan erecht24:register` again and use the newly printed secret. See the [push endpoint](/en/reference/push-endpoint/) and the [Artisan commands](/en/reference/artisan-commands/).

## Push returns 503

**Symptom:** The push endpoint answers `503`.

**Cause:** `ERECHT24_PUSH_SECRET` is not configured. The endpoint never accepts pushes without a secret; a configured secret is compared with `hash_equals`.

**Fix:** Run `php artisan erecht24:register` in this environment, set the printed secret as `ERECHT24_PUSH_SECRET` (or use `--write-env`) and run `php artisan config:cache`. See the [configuration reference](/en/reference/configuration/).

## Push route returns 404 after changing the push path

**Symptom:** After changing `ERECHT24_PUSH_PATH` or `ERECHT24_PUSH_ENABLED`, the push URL answers `404`.

**Cause:** When routes are cached, the package does not register the push route itself; it is part of the route cache, which still contains the old path or no route at all. `ERECHT24_PUSH_ENABLED=false` also removes the route.

**Fix:** Make sure `ERECHT24_PUSH_ENABLED` is not `false`, then run `php artisan route:cache` again. Check the result with `php artisan route:list --name=erecht24`. If the path changed, run `php artisan erecht24:register` so eRecht24 learns the new URL.

## Pushes are accepted but texts never update

**Symptom:** The push endpoint answers `200` with `queued`, but the stored texts stay unchanged.

**Cause:** The endpoint only dispatches a `SyncLegalTextJob`; the texts are fetched by the queue worker. No worker is running, or it still runs old code after a deployment.

**Fix:** Run `php artisan queue:work` under a process manager and restart it after each deployment with `php artisan queue:restart`. For small sites set `QUEUE_CONNECTION=sync`. To check the connection to eRecht24 independently of the queue, run `php artisan erecht24:sync`. See [Queue and sync](/en/guides/queue-and-sync/).

## `erecht24:register` refuses the URL

**Symptom:** `php artisan erecht24:register` aborts with a message that it refuses to register the push URI.

**Cause:** The push URL (`APP_URL` plus `ERECHT24_PUSH_PATH`) points to a local or private host such as `localhost` or a private IP address, or it is not an absolute `http(s)` URL with a host. eRecht24 cannot reach such addresses. The package throws `LocalPushUriException` (see the [exceptions reference](/en/reference/exceptions/)).

**Fix:** Set `APP_URL` to the public HTTPS URL of the environment. For local development use a public tunnel (for example ngrok or Expose) and pass it explicitly: `php artisan erecht24:register --push-uri=https://example.test/api/erecht24/push`.

## `erecht24:register` refuses a fourth client

**Symptom:** `php artisan erecht24:register` refuses to register and lists existing clients with their IDs.

**Cause:** eRecht24 allows at most three push clients per project, for example local (tunnel), staging and production. The package throws `TooManyPushClientsException`.

**Fix:** Free a slot with `php artisan erecht24:unregister <client-id>` and register again. Re-registering the same URL updates the existing client and does not use an additional slot.

## "This legal text is currently not available."

**Symptom:** A Blade component renders "This legal text is currently not available." instead of the text.

**Cause:** No text is stored for the requested type. This happens before the first sync, on a new environment, or when `ERECHT24_DISK` or the storage directory changed: stored texts are not part of your repository.

**Fix:** Run `php artisan erecht24:sync` and check the result with `php artisan erecht24:status`. With `APP_DEBUG=true` the placeholder additionally shows a developer hint. See [Queue and sync](/en/guides/queue-and-sync/).

## Texts differ between web servers

**Symptom:** Different web servers render different or missing legal texts.

**Cause:** Each server uses its own local disk. A push is processed by one queue worker, so only that server's disk is updated.

**Fix:** Use a shared disk, for example S3, through `ERECHT24_DISK` on all servers, then run `php artisan erecht24:sync` once. See the [configuration reference](/en/reference/configuration/).

## eRecht24 cannot reach the push URL

**Symptom:** `php artisan erecht24:status --test-push` fails, or eRecht24 does not deliver pushes, although the secret and routes are correct.

**Cause:** The environment is not reachable from the public internet, for example on staging behind HTTP basic auth, a VPN or an IP allowlist.

**Fix:** Exempt the push path from the protection, or use another environment for push. The URL must be public HTTPS. Verify with `php artisan erecht24:status --test-push`.
