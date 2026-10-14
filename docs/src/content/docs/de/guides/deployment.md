---
title: "Deployment"
description: "Deployment-Checkliste für jede Umgebung."
sidebar:
  order: 4
---

Checkliste für jede Umgebung:

- [ ] `APP_URL` ist die öffentliche HTTPS-URL dieser Umgebung.
- [ ] `ERECHT24_API_KEY` und `ERECHT24_PLUGIN_KEY` sind gesetzt.
- [ ] `php artisan erecht24:register` wurde in dieser Umgebung ausgeführt und `ERECHT24_PUSH_SECRET` ist gesetzt.
- [ ] `php artisan config:cache` (und `route:cache`, falls verwendet) lief nach der letzten Änderung an der `.env`.
- [ ] Ein Queue-Worker läuft und wurde nach dem Deployment neu gestartet (`php artisan queue:restart`), oder `QUEUE_CONNECTION=sync`.
- [ ] `php artisan erecht24:sync` lief einmal; `php artisan erecht24:status` zeigt alle Texte als gespeichert.
- [ ] `php artisan erecht24:status --test-push` meldet einen erfolgreichen Test-Push.

Wenn etwas nicht funktioniert, lesen Sie die [Fehlerbehebung](/de/project/troubleshooting/). Sie behandelt Config-Cache, Route-Cache, öffentliche HTTPS-Erreichbarkeit, das Limit von drei Push-Clients, mehrere Webserver sowie neue Umgebungen oder Speicherorte.
