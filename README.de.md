# erecht24-laravel

[English](README.md) | **Deutsch**

Laravel-Integration für die Rechtstexte-API von [eRecht24](https://www.e-recht24.de). Das Paket hält Impressum, Datenschutzerklärung und Datenschutzerklärung für soziale Medien mit Ihrem eRecht24-Projekt synchron und gibt sie aus lokalen Dateien aus:

1. eRecht24 sendet eine Push-Benachrichtigung an Ihre Anwendung, sobald sich ein Text ändert.
2. Der Push-Endpunkt prüft das gemeinsame Secret und stellt einen Sync-Job in die Queue.
3. Der Job holt den Text über die API und speichert ihn als reines HTML auf einer Filesystem-Disk.
4. Blade-Komponenten oder die `ERecht24`-Facade lesen die gespeicherte Datei. Seitenaufrufe rufen nie die API auf.

## Inhalt

- [Voraussetzungen](#voraussetzungen)
- [Installation](#installation)
- [Konfiguration](#konfiguration)
- [Schnellstart](#schnellstart)
- [Einrichtung Schritt für Schritt](#einrichtung-schritt-für-schritt)
- [Anforderungen an die Push-URL](#anforderungen-an-die-push-url)
- [Queue-Worker und erster Sync](#queue-worker-und-erster-sync)
- [Verwendung](#verwendung)
- [Push-Endpunkt](#push-endpunkt)
- [Artisan-Befehle](#artisan-befehle)
- [Deployment-Checkliste und Stolperfallen](#deployment-checkliste-und-stolperfallen)
- [Sicherheit](#sicherheit)
- [Umstieg von pirabyte/erecht24-laravel](#umstieg-von-pirabyteerecht24-laravel)
- [Entwicklung](#entwicklung)
- [Haftungsausschluss](#haftungsausschluss)
- [Lizenz](#lizenz)

## Voraussetzungen

- PHP 8.3 oder neuer
- Laravel 12 oder 13
- Ein eRecht24-Konto mit einem Projekt im eRecht24 Projekt Manager, dessen API-Schlüssel und ein Plugin-Schlüssel
- Eine URL Ihrer Anwendung, die aus dem öffentlichen Internet erreichbar ist (für Push-Benachrichtigungen)
- Ein Queue-Worker oder `QUEUE_CONNECTION=sync`
- Eine Filesystem-Disk für die gespeicherten Texte (die Standard-Disk `local` genügt für einen einzelnen Server)

## Installation

```bash
composer require kaihempel/erecht24-laravel
php artisan vendor:publish --tag=erecht24-config
```

Service-Provider und der Facade-Alias `ERecht24` werden automatisch erkannt. Das Veröffentlichen der Konfigurationsdatei ist optional; alle Einstellungen lassen sich über `.env` steuern.

## Konfiguration

### `.env`-Referenz

| Variable | Standard | Beschreibung |
|---|---|---|
| `ERECHT24_API_KEY` | – | **Pflicht.** API-Schlüssel Ihres eRecht24-Projekts. |
| `ERECHT24_PLUGIN_KEY` | – | **Pflicht.** Plugin-Schlüssel, der bei jeder API-Anfrage mitgesendet wird. |
| `ERECHT24_PUSH_SECRET` | – | **Pflicht für Push.** Secret, das `erecht24:register` ausgibt. Ohne Secret antwortet der Push-Endpunkt mit `503`. |
| `ERECHT24_PUSH_PATH` | `/api/erecht24/push` | Pfad des Push-Endpunkts. Ergibt zusammen mit `APP_URL` die Push-URL, die bei eRecht24 registriert wird. |
| `ERECHT24_PUSH_ENABLED` | `true` | Mit `false` wird die Push-Route nicht registriert. |
| `ERECHT24_PUSH_RATE_LIMIT` | `30` | Maximale Anzahl Push-Anfragen pro Minute und IP-Adresse. Muss eine positive Ganzzahl sein. |
| `ERECHT24_AUTHOR_MAIL` | – | Optionale Kontakt-E-Mail, die bei der Registrierung des Push-Clients an eRecht24 übermittelt wird. |
| `ERECHT24_BASE_URL` | `https://api.e-recht24.de/v2` | Basis-URL der API. |
| `ERECHT24_TEXT_LANGUAGES` | `de,en` | Kommagetrennte Sprachen, die synchronisiert und ausgegeben werden. Nur `de` und `en` werden unterstützt. Der erste Eintrag ist die Fallback-Sprache. |
| `ERECHT24_DISK` | `local` | Filesystem-Disk für die gespeicherten Texte. |
| `ERECHT24_DIRECTORY` | `erecht24` | Verzeichnis auf dieser Disk. |
| `ERECHT24_TIMEOUT` | `10` | HTTP-Timeout in Sekunden für API-Anfragen. |
| `ERECHT24_SYNC_TRIES` | `3` | Anzahl der Versuche des Sync-Jobs in der Queue. |

Fehlende Zugangsdaten lösen eine `MissingConfigurationException` aus, sobald sie zum ersten Mal verwendet werden, nicht beim Booten der Anwendung. Ungültige Werte (etwa eine nicht unterstützte Sprache oder ein nicht numerisches Rate-Limit) lösen eine `InvalidConfigurationException` aus.

### Einstellungen ohne Umgebungsvariable

Diese Schlüssel gibt es nur in der veröffentlichten `config/erecht24.php`:

| Schlüssel | Standard | Beschreibung |
|---|---|---|
| `queue.connection` | `null` | Queue-Verbindung für den Sync-Job. `null` verwendet die Standardverbindung der Anwendung. |
| `queue.name` | `null` | Queue-Name für den Sync-Job. `null` verwendet die Standard-Queue der Verbindung. |
| `sync.backoff` | `[60, 300, 900]` | Wartezeit in Sekunden zwischen den Versuchen des Sync-Jobs. |

## Schnellstart

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

```blade
<x-erecht24::imprint />
```

Lassen Sie einen Queue-Worker laufen (`php artisan queue:work`), damit Push-Benachrichtigungen verarbeitet werden. Die folgenden Abschnitte erklären jeden Schritt.

## Einrichtung Schritt für Schritt

1. **Projekt und API-Schlüssel anlegen.** Legen Sie im eRecht24 Projekt Manager ein Projekt für die Website an (oder öffnen Sie das bestehende), pflegen Sie die Rechtstexte und erzeugen Sie dafür einen API-Schlüssel. Außerdem benötigen Sie einen Plugin-Schlüssel.
2. **`.env` konfigurieren.** Setzen Sie `ERECHT24_API_KEY`, `ERECHT24_PLUGIN_KEY` und eine korrekte `APP_URL`. Optional setzen Sie `ERECHT24_AUTHOR_MAIL`, `ERECHT24_TEXT_LANGUAGES` und die Speicher-Einstellungen.
3. **Push-Client registrieren.**

   ```bash
   php artisan erecht24:register
   ```

   Der Befehl registriert `APP_URL` + `ERECHT24_PUSH_PATH` (zum Beispiel `https://www.example.com/api/erecht24/push`) als Push-Client bei eRecht24 oder aktualisiert den vorhandenen Client mit derselben URL. Er gibt aus:

   ```text
   Registered push client id=123
   ERECHT24_PUSH_SECRET=...
   ```

   Das Secret wird nur einmal angezeigt.
4. **Secret speichern.** Kopieren Sie die ausgegebene Zeile in die `.env`. Alternativ führen Sie den Befehl mit `--write-env` aus; dann schreibt er `ERECHT24_PUSH_SECRET` direkt in die `.env` der Anwendung. Kann die Datei nicht geschrieben werden, wird das Secret stattdessen ausgegeben.
5. **Konfiguration cachen.**

   ```bash
   php artisan config:cache
   ```

   Wenn Sie die Konfiguration in dieser Umgebung nicht cachen, wird der neue Wert beim nächsten Request übernommen.
6. **Integration prüfen.**

   ```bash
   php artisan erecht24:status --test-push
   ```

   Der Bericht zeigt, ob API-Schlüssel, Plugin-Schlüssel und Push-Secret gesetzt sind, die konfigurierten Sprachen, die registrierten Push-Clients und die gespeicherten Texte. `--test-push` lässt eRecht24 einen `ping`-Push an den Client senden, der zur aktuellen Push-URL passt. Ein erfolgreicher Test-Push bestätigt, dass eRecht24 Ihren Endpunkt erreicht und das Secret übereinstimmt.
7. **Texte einmalig abrufen.**

   ```bash
   php artisan erecht24:sync
   ```

   Ab jetzt kommen Änderungen aus dem Projekt Manager per Push an.

## Anforderungen an die Push-URL

eRecht24 muss die Push-URL aus dem Internet erreichen können:

- Sie muss eine absolute `http(s)`-URL sein. Verwenden Sie HTTPS: Das Secret wird im Request-Body übertragen. (`erecht24:register` erzwingt das Schema nicht.)
- `erecht24:register` lehnt Hosts ab, die von außen nicht erreichbar sein können: `localhost`, Hosts mit der Endung `.test`, `.local` oder `.localhost` sowie Loopback-, private oder reservierte IP-Adressen. Hostnamen werden nicht per DNS aufgelöst; ein öffentlich wirkender Name, der auf ein privates Netz zeigt, wird also nicht erkannt.
- Standardmäßig wird die URL aus `APP_URL` und `ERECHT24_PUSH_PATH` gebildet. Mit `--push-uri=` registrieren Sie eine andere URL.

**Lokale Entwicklung:** Machen Sie Ihre lokale Anwendung über einen Tunnel wie [ngrok](https://ngrok.com), [Expose](https://expose.dev) oder `cloudflared` erreichbar und registrieren Sie die Tunnel-URL:

```bash
php artisan erecht24:register --push-uri=https://abc123.ngrok-free.app/api/erecht24/push
```

`erecht24:status --test-push` und `erecht24:unregister` (ohne ID) suchen den Client anhand von `APP_URL` + `ERECHT24_PUSH_PATH`. Wenn Sie eine URL mit `--push-uri` registriert haben, setzen Sie `APP_URL` auf dieselbe Basis-URL, damit diese Befehle den Client finden, oder übergeben Sie `erecht24:unregister` die Client-ID.

Zum lokalen Ausprobieren brauchen Sie keinen Push: `php artisan erecht24:sync` holt die Texte bei Bedarf.

## Queue-Worker und erster Sync

Der Push-Endpunkt stellt nur einen `SyncLegalTextJob` in die Queue; abgerufen werden die Texte vom Queue-Worker. Ohne laufenden Worker werden Pushes angenommen, aber nichts aktualisiert.

```bash
php artisan queue:work
```

Betreiben Sie den Worker unter einem Prozessmanager (Supervisor, systemd, Laravel-Forge-Daemons usw.). Bei kleinen Websites können Sie Jobs stattdessen direkt ausführen lassen:

```dotenv
QUEUE_CONNECTION=sync
```

Der Job

- läuft auf der Verbindung und Queue aus `queue.connection` / `queue.name` (Standardwerte der Anwendung bei `null`),
- wird `ERECHT24_SYNC_TRIES`-mal versucht, mit den Wartezeiten aus `sync.backoff`,
- ist pro Texttyp eindeutig, sodass wiederholte Pushes für denselben Typ zu einem Lauf zusammenfallen (eindeutige Jobs brauchen einen Cache-Store mit atomaren Locks, etwa `redis`, `database`, `file` oder `array`),
- lässt den bisher gespeicherten Text unverändert und schreibt eine Warnung ins Log, wenn er endgültig fehlschlägt.

Ein Push aktualisiert nur den geänderten Typ. Führen Sie nach der Installation und bei jedem Deployment in eine neue Umgebung oder auf einen neuen Speicherort den ersten Sync aus:

```bash
php artisan erecht24:sync              # alle drei Typen
php artisan erecht24:sync imprint      # imprint, privacyPolicy oder privacyPolicySocialMedia
```

`erecht24:sync` läuft synchron und braucht keinen Queue-Worker.

## Verwendung

### Blade-Komponenten

```blade
<x-erecht24::imprint />
<x-erecht24::privacy-policy lang="en" class="prose" />
<x-erecht24::privacy-policy-social-media />

{{-- generische Form --}}
<x-erecht24::legal-text type="imprint" lang="de" />
```

- `type` (nur generische Komponente): `imprint`, `privacyPolicy`, `privacyPolicySocialMedia` oder die Slugs `privacy-policy` und `privacy-policy-social-media`. Jeder andere Wert löst eine `\InvalidArgumentException` aus, die die erlaubten Werte nennt.
- `lang` (optional): `de` oder `en`. Regionale Formen wie `de_DE` oder `en-US` werden auf die Hauptsprache reduziert.
- Weitere Attribute wie `class` landen am umschließenden `<div>`, das zusätzlich ein `lang`-Attribut mit der tatsächlich ausgegebenen Sprache trägt.
- Beim Rendern wird nur die gespeicherte Datei gelesen, nie die API aufgerufen.

**Sprachauswahl:** das `lang`-Attribut, dann die Locale der Anwendung (`app()->getLocale()`), dann die erste Sprache in `ERECHT24_TEXT_LANGUAGES`. Berücksichtigt werden nur konfigurierte Sprachen. Hat keine davon gespeicherten Inhalt, wird die nächste konfigurierte Sprache mit Inhalt verwendet.

**Fehlender Text:** Ist für den Typ kein Text gespeichert, rendert die View `erecht24::missing` einen kurzen neutralen Hinweis („This legal text is currently not available.“), statt eine Exception auszulösen. Mit `APP_DEBUG=true` zeigt sie zusätzlich den Hinweis, `php artisan erecht24:sync` auszuführen. Lesefehler werden geloggt und wie ein fehlender Text behandelt.

### Views anpassen

```bash
php artisan vendor:publish --tag=erecht24-views
```

Die Views werden nach `resources/views/vendor/erecht24/` kopiert und haben dann Vorrang vor den Paket-Views: `components/legal-text`, `imprint`, `privacy-policy`, `privacy-policy-social-media` und `missing`. Die Inhalts-Views erhalten `$content`, `$type` (ein `LegalTextType`) und `$lang`. Lassen Sie `{!! $content !!}` unescaped, sonst erscheint das HTML als Text. Den englischen Hinweistext in `missing` können Sie hier übersetzen.

### Facade und Inertia

Die Facade `ERecht24` (dahinter steht `KaiHempel\ERecht24\Erecht24Manager`) bietet programmatischen Zugriff auf die gespeicherten Texte. Sie verwendet dieselbe Sprachauswahl wie die Blade-Komponenten und ruft nie die API auf.

| Methode | Rückgabe |
|---|---|
| `ERecht24::html(LegalTextType\|string $type, ?string $lang = null)` | `?string` – gespeichertes HTML oder `null`, wenn nichts gespeichert ist |
| `ERecht24::has(LegalTextType\|string $type, ?string $lang = null)` | `bool` |
| `ERecht24::lastModified(LegalTextType\|string $type, ?string $lang = null)` | `?CarbonImmutable` – Zeitpunkt, zu dem der zurückgegebene Text lokal gespeichert wurde |
| `ERecht24::languages()` | `array<int, string>` – konfigurierte Sprachen |

`$type` ist ein `LegalTextType`-Case oder dessen Wert (`imprint`, `privacyPolicy`, `privacyPolicySocialMedia`). Andere Strings lösen eine `\InvalidArgumentException` aus.

Beispiel für einen Inertia-Controller:

```php
<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Facades\ERecht24;

final class LegalPageController
{
    public function imprint(): Response
    {
        return Inertia::render('Legal/Show', [
            'html' => ERecht24::html(LegalTextType::Imprint),
            'updatedAt' => ERecht24::lastModified(LegalTextType::Imprint)?->toIso8601String(),
        ]);
    }
}
```

Geben Sie `html` in Ihrer Seitenkomponente mit `dangerouslySetInnerHTML` (React) oder `v-html` (Vue) aus und behandeln Sie `null` (Text noch nicht synchronisiert).

### Auf Aktualisierungen reagieren: `LegalTextUpdated`

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

### API-Client und Speicher direkt verwenden

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

## Push-Endpunkt

Das Paket registriert `POST {ERECHT24_PUSH_PATH}` (Standard `/api/erecht24/push`) mit dem Routennamen `erecht24.push`. eRecht24 sendet `erecht24_secret` und `erecht24_type` (formularkodiert oder als JSON).

| Anfrage | Antwort |
|---|---|
| kein `ERECHT24_PUSH_SECRET` konfiguriert | `503` |
| Secret fehlt oder ist falsch | `403` |
| `erecht24_type=ping` | `200 {"code":200,"message":"pong"}` |
| `imprint`, `privacyPolicy`, `privacyPolicySocialMedia` | `200 {"code":200,"message":"queued"}`, stellt `SyncLegalTextJob` in die Queue |
| jeder andere Typ | `422` |

Das Secret wird zuerst geprüft (mit `hash_equals`), sodass nicht authentifizierte Aufrufer nichts über den Payload erfahren. Die Route liegt außerhalb der Middleware-Gruppe `web` (keine Session, keine Cookies, kein CSRF-Token) und ist über den Rate-Limiter `erecht24-push` auf `ERECHT24_PUSH_RATE_LIMIT` Anfragen pro Minute und IP begrenzt. Mit `ERECHT24_PUSH_ENABLED=false` wird sie nicht registriert.

## Artisan-Befehle

| Befehl | Beschreibung |
|---|---|
| `erecht24:register {--push-uri=} {--write-env}` | Registriert diese Umgebung als Push-Client oder aktualisiert den Client mit derselben (normalisierten) URL. Gibt Client-ID und neues Secret aus oder schreibt mit `--write-env` `ERECHT24_PUSH_SECRET` in die `.env`. Lehnt lokale/ungültige URLs und einen vierten Client ab. |
| `erecht24:status {--test-push}` | Zeigt, ob Zugangsdaten gesetzt sind (nie deren Werte), die Sprachen, registrierte Push-Clients und gespeicherte Texte mit Abrufzeitpunkt. `--test-push` löst einen `ping`-Test-Push an den Client aus, der zur aktuellen Push-URL passt. |
| `erecht24:unregister {client-id?} {--force}` | Löscht einen Push-Client per ID oder, ohne ID, den Client, der zur aktuellen Push-URL passt. Fragt nach einer Bestätigung, außer mit `--force`. |
| `erecht24:sync {type?}` | Holt einen Typ (`imprint`, `privacyPolicy`, `privacyPolicySocialMedia`) oder alle Typen synchron und speichert sie. Gibt geschriebene und übersprungene Sprachen aus; endet mit `1`, wenn ein Typ fehlgeschlagen ist. |

`register`, `unregister` und `sync` enden bei Fehlern mit Exit-Code `1`. Jede Aktualisierung eines bestehenden Clients über `erecht24:register` erzeugt ein neues Secret.

## Deployment-Checkliste und Stolperfallen

Checkliste für jede Umgebung:

- [ ] `APP_URL` ist die öffentliche HTTPS-URL dieser Umgebung.
- [ ] `ERECHT24_API_KEY` und `ERECHT24_PLUGIN_KEY` sind gesetzt.
- [ ] `php artisan erecht24:register` wurde in dieser Umgebung ausgeführt und `ERECHT24_PUSH_SECRET` ist gesetzt.
- [ ] `php artisan config:cache` (und `route:cache`, falls verwendet) lief nach der letzten Änderung an der `.env`.
- [ ] Ein Queue-Worker läuft und wurde nach dem Deployment neu gestartet (`php artisan queue:restart`), oder `QUEUE_CONNECTION=sync`.
- [ ] `php artisan erecht24:sync` lief einmal; `php artisan erecht24:status` zeigt alle Texte als gespeichert.
- [ ] `php artisan erecht24:status --test-push` meldet einen erfolgreichen Test-Push.

Stolperfallen:

- **Config-Cache.** Bei gecachter Konfiguration wirken Änderungen an der `.env` (auch das von `--write-env` geschriebene Secret) erst nach `php artisan config:cache`. Ein veraltetes Secret lässt jeden Push mit `403` scheitern.
- **Route-Cache.** Sind die Routen gecacht, registriert das Paket die Push-Route nicht selbst; sie ist dann Teil des Route-Caches. Führen Sie nach einer Änderung von `ERECHT24_PUSH_PATH` oder `ERECHT24_PUSH_ENABLED` erneut `php artisan route:cache` aus. Prüfen lässt sich das mit `php artisan route:list --name=erecht24`.
- **Öffentliches HTTPS.** eRecht24 erreicht keine Staging-Umgebungen hinter HTTP-Basic-Auth, VPN oder IP-Allowlist. Nehmen Sie den Push-Pfad davon aus oder verwenden Sie für Push eine andere Umgebung.
- **Drei Push-Clients pro Projekt.** eRecht24 erlaubt höchstens drei Push-Clients pro Projekt, zum Beispiel lokal (Tunnel), Staging und Produktion. `erecht24:register` lehnt einen vierten Client ab und listet die vorhandenen mit ihren IDs auf. Geben Sie mit `php artisan erecht24:unregister <client-id>` einen Platz frei und registrieren Sie dann erneut. Eine erneute Registrierung derselben URL aktualisiert den vorhandenen Client und belegt keinen weiteren Platz.
- **Mehrere Server.** Ein Push wird von einem einzelnen Queue-Worker verarbeitet. Wenn mehrere Webserver die Texte ausgeben, verwenden Sie über `ERECHT24_DISK` eine gemeinsame Disk (zum Beispiel S3).
- **Neue Umgebung oder neuer Speicher.** Gespeicherte Texte sind nicht Teil Ihres Repositorys. Führen Sie `php artisan erecht24:sync` nach dem ersten Deployment und nach jeder Änderung von Disk oder Verzeichnis aus.

## Sicherheit

- **Push-Secret.** Behandeln Sie `ERECHT24_PUSH_SECRET` wie ein Passwort. Bewahren Sie es in der `.env` oder Ihrem Secret-Manager auf und committen Sie es nie. Jede Umgebung erhält über ihre eigene Registrierung ein eigenes Secret.
- **Rotation.** Führen Sie `php artisan erecht24:register` (mit derselben Push-URL) erneut aus. eRecht24 stellt ein neues Secret aus, das alte ist sofort ungültig; aktualisieren Sie umgehend die `.env` und führen Sie `php artisan config:cache` aus.
- **Prüfung.** Der Endpunkt vergleicht das Secret mit `hash_equals` in konstanter Zeit und antwortet mit `503`, wenn kein Secret konfiguriert ist; Pushes ohne Secret werden also nie angenommen.
- **Keine Secrets in Logs oder Ausgaben.** Weder das Push-Secret noch der Push-Payload werden geloggt. Exception-Meldungen enthalten nie API-Schlüssel, Plugin-Schlüssel oder Secret. `erecht24:status` meldet nur, ob Zugangsdaten gesetzt sind, und Push-URLs werden ohne Benutzerinformationen und Query-String angezeigt. `erecht24:register` gibt das Secret bewusst einmal aus.
- **Vertrauenswürdiges HTML.** Die Texte sind HTML aus der eRecht24-API und werden bewusst unescaped (`{!! !!}`) und unbereinigt ausgegeben. Leiten Sie niemals Benutzereingaben durch diese Views oder die Ausgabe der Facade.
- **Kein ausführbarer Speicher.** Texte werden nur als `.html`-Dateien plus `.meta.json`-Metadaten gespeichert, nie als `.php` oder `.blade.php`. Gespeicherter Inhalt wird daher nie ausgeführt oder als Template kompiliert.

## Umstieg von pirabyte/erecht24-laravel

Dieses Paket ersetzt `pirabyte/erecht24-laravel`, ist aber kein Drop-in-Ersatz. Das alte Paket hat Texte bei Bedarf abgerufen und im Laravel-Cache gehalten; dieses Paket empfängt Pushes, synchronisiert Texte über die Queue und speichert sie als Dateien.

### Schritte

1. `composer remove pirabyte/erecht24-laravel` und `composer require kaihempel/erecht24-laravel`.
2. Löschen Sie Ihre veröffentlichte `config/erecht24.php` und veröffentlichen Sie die neue (`php artisan vendor:publish --tag=erecht24-config`). Die alten Schlüssel werden ignoriert.
3. Passen Sie die `.env` an (siehe Tabelle unten) und folgen Sie dann der [Einrichtung Schritt für Schritt](#einrichtung-schritt-für-schritt).
4. Ersetzen Sie Namespaces, Facade-Aufrufe, Typwerte und Exceptions in Ihrem Code wie unten aufgeführt.

### Entfernte und geänderte Funktionen

| Alt (`pirabyte/erecht24-laravel`) | Neu (`kaihempel/erecht24-laravel`) |
|---|---|
| PHP ^8.2, Laravel 10–13 | PHP ^8.3, Laravel 12–13. PHP 8.2 sowie Laravel 10/11 werden nicht mehr unterstützt. |
| Namespace `Pirabyte\ERecht24Laravel\` | Namespace `KaiHempel\ERecht24\` |
| Wrapper um das SDK `erecht24/rechtstexte-sdk` | Das SDK wird nicht mehr verwendet; das Paket spricht die API direkt über den HTTP-Client von Laravel an. |
| Abruf bei Bedarf: jeder Aufruf holte die Daten von der API, zwischengespeichert im Laravel-Cache | Push + Queue + Speicher: Texte liegen als Dateien auf `ERECHT24_DISK` und werden von dort gelesen. Eine öffentliche Push-URL, ein Queue-Worker (oder `QUEUE_CONNECTION=sync`) und eine Disk sind erforderlich. |
| `ERECHT24_CACHE_ENABLED`, `ERECHT24_CACHE_STORE`, `ERECHT24_CACHE_TTL`, `ERECHT24_CACHE_PREFIX` | Entfernt. Es gibt keine Laravel-Cache-Schicht. |
| `ERECHT24_LANGUAGE` | Entfernt. Die Sprache ergibt sich aus dem Argument `lang`, dann der Locale der Anwendung, dann dem ersten Eintrag von `ERECHT24_TEXT_LANGUAGES`. |
| `ERECHT24_PLUGIN_KEY` optional | `ERECHT24_PLUGIN_KEY` ist Pflicht. |
| – | Neu: `ERECHT24_PUSH_SECRET`, `ERECHT24_PUSH_PATH`, `ERECHT24_PUSH_ENABLED`, `ERECHT24_PUSH_RATE_LIMIT`, `ERECHT24_AUTHOR_MAIL`, `ERECHT24_BASE_URL`, `ERECHT24_TEXT_LANGUAGES`, `ERECHT24_DISK`, `ERECHT24_DIRECTORY`, `ERECHT24_TIMEOUT`, `ERECHT24_SYNC_TRIES` |
| `ERecht24::imprint($lang)` | `ERecht24::html(LegalTextType::Imprint, $lang)` |
| `ERecht24::privacyPolicy($lang)` | `ERecht24::html(LegalTextType::PrivacyPolicy, $lang)` |
| `ERecht24::privacyPolicySocialMedia($lang)` | `ERecht24::html(LegalTextType::PrivacyPolicySocialMedia, $lang)` |
| `ERecht24::document($type, $lang)` mit Rückgabe `LegalTextData` | `ERecht24::html()` für das gespeicherte HTML und `ERecht24::lastModified()` für den lokalen Speicherzeitpunkt. Für die vollständige Live-Antwort der API verwenden Sie `Erecht24Client::legalText()`, das ein `KaiHempel\ERecht24\DTOs\LegalText` zurückgibt. |
| `ERecht24::html($type, $lang)` (Live-API-Aufruf, gecacht) | `ERecht24::html($type, $lang)` liest die gespeicherte Datei und gibt `null` zurück, wenn der Text noch nicht synchronisiert wurde. |
| `ERecht24::isConfigured()` | Entfernt. Verwenden Sie `php artisan erecht24:status` oder `hasApiKey()`, `hasPluginKey()` und `hasPushSecret()` von `KaiHempel\ERecht24\Config\Erecht24Settings`. `ERecht24::has()` prüft, ob ein Text gespeichert ist. |
| `ERecht24::clearCache($type)` | Entfernt (kein Cache). Neu abrufen mit `php artisan erecht24:sync`; eigene Caches leeren Sie über einen Listener auf `LegalTextUpdated`. |
| DTO `LegalTextData` (`html`, `htmlDe`, `htmlEn`, `warnings`, `createdAt`, `modifiedAt`, `pushedAt`, `language`) | Entfernt. Die Facade liefert Strings. `Erecht24Client::legalText()` liefert `LegalText` mit `htmlDe`, `htmlEn`, `created`, `modified`, `pushed`, `warnings` und `html($language)`. |
| Typwerte `imprint`, `privacy_policy`, `privacy_policy_social_media` | `imprint`, `privacyPolicy`, `privacyPolicySocialMedia` (die Namen der Enum-Cases sind unverändert). `LegalTextType::fromValue()` entfällt; verwenden Sie `from()` / `tryFrom()`. |
| Enum `Language` mit `normalize()` | Entfernt. Sprachen sind einfache Strings (`de`, `en`); regionale Formen werden automatisch reduziert. |
| Container-Binding `'erecht24'`, Contract `LegalTextClient`, `SdkLegalTextClient` | Entfernt. Die Facade löst `Erecht24Manager` auf; der API-Client ist `Erecht24Client`. In Tests faken Sie HTTP mit `Http::fake()`. |
| `ERecht24Exception` | `Erecht24ApiException` (API-Fehler), `Erecht24AuthenticationException` (401), `InvalidConfigurationException`, `LegalTextStoreException` |
| `MissingApiKeyException` | `MissingConfigurationException` (API-Schlüssel, Plugin-Schlüssel oder Push-Secret fehlt) |
| `UnsupportedLegalTextTypeException` | `\InvalidArgumentException` |

Neue Funktionen ohne Entsprechung im alten Paket: Push-Endpunkt, Sync-Job in der Queue, Artisan-Befehle (`erecht24:register`, `erecht24:status`, `erecht24:unregister`, `erecht24:sync`), Blade-Komponenten und das Event `LegalTextUpdated`.

## Entwicklung

```bash
composer test      # Pest
composer lint      # Pint (zum Korrigieren: composer format)
composer analyse   # PHPStan Level 8
```

Änderungen stehen in `CHANGELOG.md`, die Feature-Spezifikationen unter `specs/`.

## Haftungsausschluss

Dieses Paket steht in keiner Verbindung zu eRecht24 und wird von eRecht24 weder unterstützt noch gesponsert. eRecht24 ist eine Marke des jeweiligen Inhabers.

Dieses Paket ist technische Integrationssoftware und keine Rechtsberatung. Sie sind selbst für den Inhalt Ihrer Rechtstexte sowie für Ihr eRecht24-Konto und die API-Einrichtung verantwortlich.

## Lizenz

MIT, siehe [`LICENSE.md`](LICENSE.md).
