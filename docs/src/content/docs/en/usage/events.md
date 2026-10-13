---
title: "Events"
description: "React to synced legal texts by listening for the LegalTextUpdated event."
sidebar:
  order: 3
---

After a sync has written at least one language, `KaiHempel\ERecht24\Events\LegalTextUpdated` is dispatched with:

- `type` – the `LegalTextType` that was updated
- `languages` – `array<int, string>` of the language codes that were written

Languages for which the API returned no content are skipped and keep their previous file.

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

With Laravel's event discovery the listener is registered automatically; otherwise register it with `Event::listen(LegalTextUpdated::class, ForgetLegalPageCache::class)` in a service provider.
