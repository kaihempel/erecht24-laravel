---
title: "Deployment"
description: "Deployment checklist for each environment."
sidebar:
  order: 4
---

Checklist for each environment:

- [ ] `APP_URL` is the public HTTPS URL of this environment.
- [ ] `ERECHT24_API_KEY` and `ERECHT24_PLUGIN_KEY` are set.
- [ ] `php artisan erecht24:register` has been run in this environment and `ERECHT24_PUSH_SECRET` is set.
- [ ] `php artisan config:cache` (and `route:cache`, if you use it) ran after the last `.env` change.
- [ ] If a trust seal is configured: `php artisan vendor:publish --tag=erecht24-assets --force` ran, so the seal images in `public/vendor/erecht24/` exist and match the installed package version (see [Trust seals](/en/usage/blade-components/#trust-seals)).
- [ ] A queue worker is running and was restarted after the deployment (`php artisan queue:restart`), or `QUEUE_CONNECTION=sync`.
- [ ] `php artisan erecht24:sync` ran once; `php artisan erecht24:status` lists all texts as stored.
- [ ] `php artisan erecht24:status --test-push` reports a successful test push.

If something goes wrong, see [Troubleshooting](/en/project/troubleshooting/). It covers the config cache, the route cache, public HTTPS reachability, the limit of three push clients, multiple web servers and new environments or storage locations.
