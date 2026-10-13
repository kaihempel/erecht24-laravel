---
title: "Events"
description: "Reagieren Sie auf synchronisierte Rechtstexte, indem Sie das Event LegalTextUpdated abonnieren."
sidebar:
  order: 3
---

Nachdem ein Sync mindestens eine Sprache geschrieben hat, wird `KaiHempel\ERecht24\Events\LegalTextUpdated` ausgelöst, mit:

- `type` – der aktualisierte `LegalTextType`
- `languages` – `array<int, string>` mit den geschriebenen Sprachcodes

Sprachen, für die die API keinen Inhalt geliefert hat, werden übersprungen und behalten ihre bisherige Datei.

```php
<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Cache;
use KaiHempel\ERecht24\Events\LegalTextUpdated;

final class ForgetLegalPageCache
{
    public function handle(LegalTextUpdated $event): void
    {
        foreach ($event->languages as $language) {
            Cache::forget("legal-page.{$event->type->value}.{$language}");
        }
    }
}
```

Mit der Event-Discovery von Laravel wird der Listener automatisch registriert; andernfalls registrieren Sie ihn mit `Event::listen(LegalTextUpdated::class, ForgetLegalPageCache::class)` in einem Service-Provider.
