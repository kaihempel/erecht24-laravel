---
title: "Queue and sync"
description: "Run the queue worker that processes pushes and perform the initial sync."
sidebar:
  order: 3
---

The push endpoint only dispatches a `SyncLegalTextJob`; the texts are fetched by the queue worker. Without a running worker, pushes are accepted but nothing is updated.

```bash
php artisan queue:work
```

Run the worker under a process manager (Supervisor, systemd, Laravel Forge daemons, etc.).

:::tip
For small sites you can process jobs inline instead:

```dotenv
QUEUE_CONNECTION=sync
```
:::

The job:

- runs on the connection and queue set in `queue.connection` / `queue.name` (application defaults when `null`),
- is attempted `ERECHT24_SYNC_TRIES` times with the waits from `sync.backoff`,
- is unique per text type, so repeated pushes for the same type collapse into one run (unique jobs need a cache store that supports atomic locks, such as `redis`, `database`, `file` or `array`),
- leaves the previously stored text untouched and logs a warning when it fails permanently.

A push only updates the type that changed. After installing the package, and whenever you deploy to a new environment or storage location, run the initial sync:

```bash
php artisan erecht24:sync              # all three types
php artisan erecht24:sync imprint      # imprint, privacyPolicy or privacyPolicySocialMedia
```

`erecht24:sync` runs synchronously and does not need a queue worker.
