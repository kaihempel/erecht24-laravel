# erecht24-laravel

[![Latest Stable Version](http://poser.pugx.org/kaihempel/erecht24-laravel/v)](https://packagist.org/packages/kaihempel/erecht24-laravel) [![Total Downloads](http://poser.pugx.org/kaihempel/erecht24-laravel/downloads)](https://packagist.org/packages/kaihempel/erecht24-laravel)  [![License](http://poser.pugx.org/kaihempel/erecht24-laravel/license)](https://packagist.org/packages/kaihempel/erecht24-laravel) [![PHP Version Require](http://poser.pugx.org/kaihempel/erecht24-laravel/require/php)](https://packagist.org/packages/kaihempel/erecht24-laravel)

[English](README.md) | **Deutsch**

Laravel-Integration für die Rechtstexte-API von [eRecht24](https://www.e-recht24.de). Das Paket hält Impressum, Datenschutzerklärung und Datenschutzerklärung für soziale Medien mit Ihrem eRecht24-Projekt synchron und gibt sie aus lokalen Dateien aus:

1. eRecht24 sendet eine Push-Benachrichtigung an Ihre Anwendung, sobald sich ein Text ändert.
2. Der Push-Endpunkt prüft das gemeinsame Secret und stellt einen Sync-Job in die Queue.
3. Der Job holt den Text über die API und speichert ihn als reines HTML auf einer Filesystem-Disk.
4. Blade-Komponenten oder die `ERecht24`-Facade lesen die gespeicherte Datei. Seitenaufrufe rufen nie die API auf.

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

Lassen Sie einen Queue-Worker laufen (`php artisan queue:work`), damit Push-Benachrichtigungen verarbeitet werden.

## Dokumentation

Die vollständige Dokumentation (Einrichtung, Konfiguration, Verwendung, Artisan-Befehle, Push-Endpunkt, Deployment, Sicherheit, Fehlerbehebung) finden Sie auf der Dokumentationsseite: **<https://erecht24-laravel.kuweh.de/de/>**

## Haftungsausschluss

Dieses Paket steht in keiner Verbindung zu eRecht24 und wird von eRecht24 weder unterstützt noch gesponsert. eRecht24 ist eine Marke des jeweiligen Inhabers.

Dieses Paket ist technische Integrationssoftware und keine Rechtsberatung. Sie sind selbst für den Inhalt Ihrer Rechtstexte sowie für Ihr eRecht24-Konto und die API-Einrichtung verantwortlich.

## Lizenz

MIT, siehe [`LICENSE.md`](LICENSE.md).
