---
title: "Queue und Synchronisierung"
description: "Betreiben Sie den Queue-Worker, der Pushes verarbeitet, und führen Sie den ersten Sync aus."
sidebar:
  order: 3
---

Der Push-Endpunkt stellt nur einen `SyncLegalTextJob` in die Queue; abgerufen werden die Texte vom Queue-Worker. Ohne laufenden Worker werden Pushes angenommen, aber nichts aktualisiert.

```bash
php artisan queue:work
```

Betreiben Sie den Worker unter einem Prozessmanager (Supervisor, systemd, Laravel-Forge-Daemons usw.).

:::tip
Bei kleinen Websites können Sie Jobs stattdessen direkt ausführen lassen:

```dotenv
QUEUE_CONNECTION=sync
```
:::

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
