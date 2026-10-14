---
title: "Exceptions"
description: "Alle vom Paket geworfenen Exception-Klassen: Elternklasse, Auslöser und Behandlung."
sidebar:
  order: 4
---

Alle Exceptions liegen im Namespace `KaiHempel\ERecht24\Exceptions`. Keine Exception-Meldung enthält den API-Schlüssel, den Plugin-Schlüssel oder das Push-Secret. Die Meldungen enthalten nur Namen von Konfigurationsschlüsseln, HTTP-Statuscodes, API-Meldungen, Dateipfade und maskierte Push-URLs und sind daher unbedenklich zu loggen und zu melden.

## Hierarchie

Nur `Erecht24AuthenticationException` erbt von `Erecht24ApiException`. Alle anderen Klassen erben direkt von `\RuntimeException` und haben keine gemeinsame Paket-Basisklasse, daher fängt `catch (Erecht24ApiException $e)` sie **nicht**. Fangen Sie `\RuntimeException` als grobe Auffanglösung.

## `Erecht24ApiException`

**Erbt von**: `\RuntimeException` (nicht final, kann also abgeleitet werden).

**Wird geworfen, wenn**: die eRecht24-API eine Anfrage von `Erecht24Client` mit einer fehlgeschlagenen Antwort beantwortet, zum Beispiel `404` für einen unbekannten Rechtstext oder Client oder `503`. Verbindungsfehler sowie `5xx`- und `429`-Antworten werden dreimal (im Abstand von 200 ms) wiederholt, bevor die Exception geworfen wird. Sie wird außerdem von `fireTestPush()` geworfen, wenn der Test-Push fehlschlägt: `404` bei unbekanntem Client, `496`, `497` oder `498`, wenn die Zustellung oder die Antwortvalidierung fehlschlägt, sowie `503`.

**Behandlung**: Lesen Sie die öffentlichen readonly-Eigenschaften `status` (HTTP-Statuscode), `apiMessage` (von der API gesendete Meldung oder `null`) und `debug` (von der API gesendete Debug-Daten oder `null`). Ohne API-Meldung lautet die Exception-Meldung `eRecht24 API request failed with status N`. Die Artisan-Befehle und `erecht24:status` fangen diese Exception ab und melden sie; Befehle, die fehlschlagen, beenden sich mit Exit-Code `1`.

## `Erecht24AuthenticationException`

**Erbt von**: `Erecht24ApiException`.

**Wird geworfen, wenn**: die API mit HTTP `401` antwortet. Sie hat dieselben Eigenschaften wie ihre Elternklasse.

**Behandlung**: Prüfen Sie `ERECHT24_API_KEY` und `ERECHT24_PLUGIN_KEY`. Wer `Erecht24ApiException` fängt, fängt auch diese Klasse; fangen Sie sie zuerst ab, wenn Sie Authentifizierungsfehler gesondert behandeln möchten.

## `MissingConfigurationException`

**Erbt von**: `\RuntimeException` (final).

**Wird geworfen, wenn**: ein erforderliches Credential gelesen wird, aber nicht konfiguriert ist: `api_key`, `plugin_key` oder `push_secret`. Die Prüfung erfolgt erst bei der Verwendung, nicht beim Booten der Anwendung. Auch das Auflösen von `Erecht24Client` wirft sie, wenn die Schlüssel fehlen, weil der Client die Credentials in seinem Konstruktor liest.

**Behandlung**: Setzen Sie `ERECHT24_API_KEY`, `ERECHT24_PLUGIN_KEY` und, für Push, `ERECHT24_PUSH_SECRET` (siehe [Konfiguration](/de/reference/configuration/)). Die Befehle zum Registrieren und Abmelden sowie `erecht24:status` fangen sie ab und melden den fehlenden Schlüssel.

## `InvalidConfigurationException`

**Erbt von**: `\RuntimeException` (final).

**Wird geworfen, wenn**: ein Konfigurationswert vorhanden, aber ungültig ist: `push_enabled` (kein Boolean), `push_rate_limit`, `timeout` oder `sync.tries` (keine positive Ganzzahl) oder `text_languages` (leer oder mit einer anderen Sprache als `de` und `en`). Die Meldung nennt den fehlerhaften Schlüssel und den Grund.

**Behandlung**: Korrigieren Sie den Wert in Ihrer `.env`-Datei oder in `config/erecht24.php`. Die Befehle zum Registrieren und Abmelden sowie `erecht24:status` fangen sie ab und melden sie. Die Blade-Komponenten und der Manager lösen Sprachen über `LegalTextResolver` auf, der diese Exception abfängt und stattdessen auf eine Ersatzsprache ausweicht, statt fehlzuschlagen.

## `LegalTextStoreException`

**Erbt von**: `\RuntimeException` (final).

**Wird geworfen, wenn**: `LegalTextStore` eine Datei nicht schreiben kann: Das Schreiben der temporären Datei, das Verschieben an den endgültigen Pfad oder das Schreiben der Metadaten-Datei ist fehlgeschlagen. Der ursprüngliche Fehler bleibt als vorherige Exception erhalten. Schlägt nur das Schreiben der Metadaten fehl, wird die Inhaltsdatei wieder entfernt. Die Exception wird bei einem Sync einzelner Typen weitergereicht.

**Behandlung**: Prüfen Sie, ob die konfigurierte Disk und das Verzeichnis existieren und beschreibbar sind (siehe `ERECHT24_DISK` und `ERECHT24_DIRECTORY`). `erecht24:sync` mit einem einzelnen Typ meldet den Fehler und beendet sich mit `1`. Ein Sync-Job in der Queue schlägt fehl und wird gemäß `ERECHT24_SYNC_TRIES` wiederholt. Werden alle Typen auf einmal synchronisiert, wird ein Fehler als Warnung geloggt und die übrigen Typen werden dennoch synchronisiert.

## `LocalPushUriException`

**Erbt von**: `\RuntimeException` (final).

**Wird geworfen, wenn**: ein Push-Client mit einer Push-URL registriert wird, die keine absolute `http`- oder `https`-URL mit Host ist oder auf einen lokalen oder Entwicklungs-Host zeigt, den eRecht24 nicht erreichen kann. Die URL in der Meldung ist maskiert.

**Behandlung**: Verwenden Sie eine öffentlich erreichbare URL: Setzen Sie `APP_URL` oder `ERECHT24_PUSH_PATH` korrekt, nutzen Sie einen öffentlichen Tunnel oder übergeben Sie eine explizite `--push-uri`. `erecht24:register` fängt die Exception ab, gibt die Meldung aus und beendet sich mit `1`.

## `TooManyPushClientsException`

**Erbt von**: `\RuntimeException` (final).

**Wird geworfen, wenn**: ein Push-Client registriert wird, obwohl das eRecht24-Projekt bereits die maximal erlaubten drei Clients hat und keiner davon zur aktuellen Push-URL passt.

**Behandlung**: Die öffentliche readonly-Eigenschaft `clients` enthält die vorhandenen Push-Clients (`PushClient[]`), sodass Sie diese anzeigen oder einen zum Entfernen auswählen können. Entfernen Sie einen mit `erecht24:unregister` und registrieren Sie erneut. `erecht24:register` listet die Clients auf und beendet sich mit `1`.

## `PushClientNotFoundException`

**Erbt von**: `\RuntimeException` (final).

**Wird geworfen, wenn**: ein nicht vorhandener Push-Client abgemeldet wird: Entweder ist die angegebene Client-ID unbekannt, oder kein registrierter Client passt zur aktuellen Push-URL.

**Behandlung**: Die öffentliche readonly-Eigenschaft `clientId` enthält die angeforderte ID oder `null`, wenn kein Client zur aktuellen Push-URL passte. Prüfen Sie die registrierten Clients sowie die verwendete ID oder Push-URL. `erecht24:unregister` fängt die Exception ab, gibt die Meldung aus und beendet sich mit `1`.
