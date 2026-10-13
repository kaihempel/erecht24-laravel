---
title: "Artisan-Befehle"
description: "Referenz der Befehle erecht24:register, status, unregister und sync."
sidebar:
  order: 2
---

| Befehl | Beschreibung |
|---|---|
| `erecht24:register {--push-uri=} {--write-env}` | Registriert diese Umgebung als Push-Client oder aktualisiert den Client mit derselben (normalisierten) URL. Gibt Client-ID und neues Secret aus oder schreibt mit `--write-env` `ERECHT24_PUSH_SECRET` in die `.env`. Lehnt lokale/ungültige URLs und einen vierten Client ab. |
| `erecht24:status {--test-push}` | Zeigt, ob Zugangsdaten gesetzt sind (nie deren Werte), die Sprachen, registrierte Push-Clients und gespeicherte Texte mit Abrufzeitpunkt. `--test-push` löst einen `ping`-Test-Push an den Client aus, der zur aktuellen Push-URL passt. |
| `erecht24:unregister {client-id?} {--force}` | Löscht einen Push-Client per ID oder, ohne ID, den Client, der zur aktuellen Push-URL passt. Fragt nach einer Bestätigung, außer mit `--force`. |
| `erecht24:sync {type?}` | Holt einen Typ (`imprint`, `privacyPolicy`, `privacyPolicySocialMedia`) oder alle Typen synchron und speichert sie. Gibt geschriebene und übersprungene Sprachen aus; endet mit `1`, wenn ein Typ fehlgeschlagen ist. |

`register`, `unregister` und `sync` enden bei Fehlern mit Exit-Code `1`. Jede Aktualisierung eines bestehenden Clients über `erecht24:register` erzeugt ein neues Secret.
