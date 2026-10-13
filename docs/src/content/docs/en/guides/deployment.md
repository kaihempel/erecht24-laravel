---
title: "Deployment"
description: "Deployment checklist and common pitfalls for each environment."
sidebar:
  order: 4
---

Checklist for each environment:

- [ ] `APP_URL` is the public HTTPS URL of this environment.
- [ ] `ERECHT24_API_KEY` and `ERECHT24_PLUGIN_KEY` are set.
- [ ] `php artisan erecht24:register` has been run in this environment and `ERECHT24_PUSH_SECRET` is set.
- [ ] `php artisan config:cache` (and `route:cache`, if you use it) ran after the last `.env` change.
- [ ] A queue worker is running and was restarted after the deployment (`php artisan queue:restart`), or `QUEUE_CONNECTION=sync`.
- [ ] `php artisan erecht24:sync` ran once; `php artisan erecht24:status` lists all texts as stored.
- [ ] `php artisan erecht24:status --test-push` reports a successful test push.

Pitfalls:

:::caution
**Config cache.** With a cached configuration, changes to `.env` (including the secret written by `--write-env`) take effect only after `php artisan config:cache`. A stale secret makes every push fail with `403`.
:::

:::caution
**Route cache.** When routes are cached, the package does not register the push route itself; it is part of the route cache. Run `php artisan route:cache` again after changing `ERECHT24_PUSH_PATH` or `ERECHT24_PUSH_ENABLED`. Check with `php artisan route:list --name=erecht24`.
:::

:::caution
**Public HTTPS.** eRecht24 cannot reach staging environments behind HTTP basic auth, a VPN or an IP allowlist. Exempt the push path or use another environment for push.
:::

- **Three push clients per project.** eRecht24 allows at most three push clients per project, for example local (tunnel), staging and production. `erecht24:register` refuses a fourth client and lists the existing ones with their IDs. Free a slot with `php artisan erecht24:unregister <client-id>`, then register again. Re-registering the same URL updates the existing client and does not use an additional slot.
- **Multiple servers.** The push is processed by one queue worker. If several web servers render the texts, use a shared disk (for example S3) via `ERECHT24_DISK`.
- **New environment or storage.** Stored texts are not part of your repository. Run `php artisan erecht24:sync` after the first deployment and whenever the disk or directory changes.
