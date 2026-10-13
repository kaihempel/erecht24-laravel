---
title: "Konfiguration"
description: "Referenz aller ERECHT24-Umgebungsvariablen und der Einstellungen, die nur in config/erecht24.php existieren."
sidebar:
  order: 1
---

## `.env`-Referenz

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

## Einstellungen ohne Umgebungsvariable

Diese Schlüssel gibt es nur in der veröffentlichten `config/erecht24.php`:

| Schlüssel | Standard | Beschreibung |
|---|---|---|
| `queue.connection` | `null` | Queue-Verbindung für den Sync-Job. `null` verwendet die Standardverbindung der Anwendung. |
| `queue.name` | `null` | Queue-Name für den Sync-Job. `null` verwendet die Standard-Queue der Verbindung. |
| `sync.backoff` | `[60, 300, 900]` | Wartezeit in Sekunden zwischen den Versuchen des Sync-Jobs. |
