---
title: "Schnellstart"
description: "Registrieren Sie den Push-Client, synchronisieren Sie die Texte und geben Sie das Impressum mit wenigen Befehlen aus."
sidebar:
  order: 3
---

```dotenv
APP_URL=https://www.example.com
ERECHT24_API_KEY=ihr-api-schluessel
ERECHT24_PLUGIN_KEY=ihr-plugin-schluessel
```

```bash
php artisan erecht24:register --write-env
php artisan config:cache
php artisan erecht24:status --test-push
php artisan erecht24:sync
```

:::caution
Bei gecachter Konfiguration wirken Änderungen an der `.env` (auch das von `--write-env` geschriebene Secret) erst nach `php artisan config:cache`.
:::

```blade
<x-erecht24::imprint />
```

Lassen Sie einen Queue-Worker laufen (`php artisan queue:work`), damit Push-Benachrichtigungen verarbeitet werden. Die folgenden Abschnitte erklären jeden Schritt.
