---
title: "API-Client und Store"
description: "Den eRecht24-API-Client und den Rechtstext-Speicher direkt verwenden."
sidebar:
  order: 4
---

`KaiHempel\ERecht24\Erecht24Client` ruft die API direkt auf (Live-Anfrage, es wird nichts gespeichert):

```php
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Erecht24Client;

$text = app(Erecht24Client::class)->legalText(LegalTextType::Imprint);

$text->html('de');   // ?string, ebenso $text->htmlDe / $text->htmlEn
$text->modified;     // ?string, wie von der API geliefert (ebenso created, pushed, warnings)
```

Außerdem verwaltet er Push-Clients: `listClients()`, `createClient(PushClient)`, `updateClient(PushClient)` (liefert ein neues Secret, das Sie speichern müssen), `deleteClient(int)` und `fireTestPush(int $clientId, string $type = 'ping')`. Anfragen werden bei Verbindungsfehlern, 5xx und 429 bis zu 3-mal im Abstand von 200 ms wiederholt. Fehler lösen eine `Erecht24ApiException` aus, ein `401` eine `Erecht24AuthenticationException`. Wird der Client ohne API- oder Plugin-Schlüssel aufgelöst, entsteht eine `MissingConfigurationException`.

`KaiHempel\ERecht24\Storage\LegalTextStore` liest und schreibt die gespeicherten Dateien:

```php
use KaiHempel\ERecht24\Storage\LegalTextStore;

$store = app(LegalTextStore::class);

$store->get(LegalTextType::Imprint, 'de');           // ?string
$store->has(LegalTextType::Imprint, 'de');           // bool
$store->lastModified(LegalTextType::Imprint, 'de');  // ?CarbonImmutable, lokaler Speicherzeitpunkt
$store->put(LegalTextType::Imprint, 'de', $html);
$store->forget(LegalTextType::Imprint, 'de');
```

Gespeichert wird unter `<directory>/<slug>.<lang>.html` plus `<slug>.<lang>.meta.json` mit dem Speicherzeitpunkt; der Slug ist `imprint`, `privacy-policy` oder `privacy-policy-social-media`. Mit der Standard-Disk `local` von Laravel ist das `storage/app/private/erecht24/`. Eine nicht konfigurierte Sprache löst vor jedem Plattenzugriff eine `\InvalidArgumentException` aus; Schreibfehler lösen eine `LegalTextStoreException` aus und erhalten den bisherigen Inhalt.
