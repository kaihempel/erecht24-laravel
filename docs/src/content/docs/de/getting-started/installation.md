---
title: "Installation"
description: "Installieren Sie das Paket mit Composer und veröffentlichen Sie optional die Konfigurationsdatei."
sidebar:
  order: 2
---

```bash
composer require kaihempel/erecht24-laravel
php artisan vendor:publish --tag=erecht24-config
```

Service-Provider und der Facade-Alias `ERecht24` werden automatisch erkannt. Das Veröffentlichen der Konfigurationsdatei ist optional; alle Einstellungen lassen sich über `.env` steuern.

Wenn Sie das eRecht24-Vertrauenssiegel unter Impressum oder Datenschutzerklärung anzeigen möchten, veröffentlichen Sie zusätzlich die Siegelbilder (nach jedem Paket-Update erneut, mit `--force`):

```bash
php artisan vendor:publish --tag=erecht24-assets
```

Details finden Sie unter [Vertrauenssiegel](/de/usage/blade-components/#vertrauenssiegel).
