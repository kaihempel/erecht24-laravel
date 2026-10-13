---
title: "Sicherheit"
description: "Wie das Paket Push-Secret und Zugangsdaten schützt und worauf Sie beim Umgang mit vertrauenswürdigem HTML achten müssen."
sidebar:
  order: 1
---

## Secrets und Prüfung

:::danger
- **Push-Secret.** Behandeln Sie `ERECHT24_PUSH_SECRET` wie ein Passwort. Bewahren Sie es in der `.env` oder Ihrem Secret-Manager auf und committen Sie es nie. Jede Umgebung erhält über ihre eigene Registrierung ein eigenes Secret.
- **Rotation.** Führen Sie `php artisan erecht24:register` (mit derselben Push-URL) erneut aus. eRecht24 stellt ein neues Secret aus, das alte ist sofort ungültig; aktualisieren Sie umgehend die `.env` und führen Sie `php artisan config:cache` aus.
:::

- **Prüfung.** Der Endpunkt vergleicht das Secret mit `hash_equals` in konstanter Zeit und antwortet mit `503`, wenn kein Secret konfiguriert ist; Pushes ohne Secret werden also nie angenommen.
- **Keine Secrets in Logs oder Ausgaben.** Weder das Push-Secret noch der Push-Payload werden geloggt. Exception-Meldungen enthalten nie API-Schlüssel, Plugin-Schlüssel oder Secret. `erecht24:status` meldet nur, ob Zugangsdaten gesetzt sind, und Push-URLs werden ohne Benutzerinformationen und Query-String angezeigt. `erecht24:register` gibt das Secret bewusst einmal aus.

:::danger
- **Vertrauenswürdiges HTML.** Die Texte sind HTML aus der eRecht24-API und werden bewusst nicht maskiert (`{!! !!}`) und unbereinigt ausgegeben. Leiten Sie niemals Benutzereingaben durch diese Views oder die Ausgabe der Facade.
:::

- **Kein ausführbarer Speicher.** Texte werden nur als `.html`-Dateien plus `.meta.json`-Metadaten gespeichert, nie als `.php` oder `.blade.php`. Gespeicherter Inhalt wird daher nie ausgeführt oder als Template kompiliert.

## Haftungsausschluss

Dieses Paket steht in keiner Verbindung zu eRecht24 und wird von eRecht24 weder unterstützt noch gesponsert. eRecht24 ist eine Marke des jeweiligen Inhabers.

Dieses Paket ist technische Integrationssoftware und keine Rechtsberatung. Sie sind selbst für den Inhalt Ihrer Rechtstexte sowie für Ihr eRecht24-Konto und die API-Einrichtung verantwortlich.
