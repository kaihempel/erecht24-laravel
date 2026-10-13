---
title: "Quick start"
description: "Register the push client, sync the texts and render the imprint in a few commands."
sidebar:
  order: 3
---

```dotenv
APP_URL=https://www.example.com
ERECHT24_API_KEY=your-api-key
ERECHT24_PLUGIN_KEY=your-plugin-key
```

```bash
php artisan erecht24:register --write-env
php artisan config:cache
php artisan erecht24:status --test-push
php artisan erecht24:sync
```

:::caution
With a cached configuration, changes to `.env` (including the secret written by `--write-env`) take effect only after `php artisan config:cache`.
:::

```blade
<x-erecht24::imprint />
```

Keep a queue worker running (`php artisan queue:work`) so that push notifications are processed. The following sections explain each step.
