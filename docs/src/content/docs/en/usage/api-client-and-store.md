---
title: "API client and store"
description: "Use the eRecht24 API client and the legal text store directly."
sidebar:
  order: 4
---

`KaiHempel\ERecht24\Erecht24Client` calls the API directly (live request, nothing is stored):

```php
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Erecht24Client;

$text = app(Erecht24Client::class)->legalText(LegalTextType::Imprint);

$text->html('de');   // ?string, also $text->htmlDe / $text->htmlEn
$text->modified;     // ?string, as delivered by the API (also created, pushed, warnings)
```

It also manages push clients: `listClients()`, `createClient(PushClient)`, `updateClient(PushClient)` (returns a fresh secret that you must store), `deleteClient(int)` and `fireTestPush(int $clientId, string $type = 'ping')`. Requests are retried 3 times (200 ms apart) on connection errors, 5xx and 429. Failures throw `Erecht24ApiException`; a `401` throws `Erecht24AuthenticationException`. Resolving the client without API key or plugin key throws `MissingConfigurationException`.

`KaiHempel\ERecht24\Storage\LegalTextStore` reads and writes the stored files:

```php
use KaiHempel\ERecht24\Storage\LegalTextStore;

$store = app(LegalTextStore::class);

$store->get(LegalTextType::Imprint, 'de');           // ?string
$store->has(LegalTextType::Imprint, 'de');           // bool
$store->lastModified(LegalTextType::Imprint, 'de');  // ?CarbonImmutable, local save time
$store->put(LegalTextType::Imprint, 'de', $html);
$store->forget(LegalTextType::Imprint, 'de');
```

Files are stored as `<directory>/<slug>.<lang>.html` plus a `<slug>.<lang>.meta.json` with the save time, where the slug is `imprint`, `privacy-policy` or `privacy-policy-social-media`. With Laravel's default `local` disk that is `storage/app/private/erecht24/`. A language that is not configured throws an `\InvalidArgumentException` before any disk access; write failures throw `LegalTextStoreException` and keep the previous content.
