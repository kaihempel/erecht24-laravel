---
title: "Deployment"
description: "Deployment-Checkliste und häufige Stolperfallen für jede Umgebung."
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

Stolperfallen:

:::caution
**Config-Cache.** Bei gecachter Konfiguration wirken Änderungen an der `.env` (auch das von `--write-env` geschriebene Secret) erst nach `php artisan config:cache`. Ein veraltetes Secret lässt jeden Push mit `403` scheitern.
:::

:::caution
**Route-Cache.** Sind die Routen gecacht, registriert das Paket die Push-Route nicht selbst; sie ist dann Teil des Route-Caches. Führen Sie nach einer Änderung von `ERECHT24_PUSH_PATH` oder `ERECHT24_PUSH_ENABLED` erneut `php artisan route:cache` aus. Prüfen lässt sich das mit `php artisan route:list --name=erecht24`.
:::

:::caution
**Öffentliches HTTPS.** eRecht24 erreicht keine Staging-Umgebungen hinter HTTP-Basic-Auth, VPN oder IP-Allowlist. Nehmen Sie den Push-Pfad davon aus oder verwenden Sie für Push eine andere Umgebung.
:::

- **Drei Push-Clients pro Projekt.** eRecht24 erlaubt höchstens drei Push-Clients pro Projekt, zum Beispiel lokal (Tunnel), Staging und Produktion. `erecht24:register` lehnt einen vierten Client ab und listet die vorhandenen mit ihren IDs auf. Geben Sie mit `php artisan erecht24:unregister <client-id>` einen Platz frei und registrieren Sie dann erneut. Eine erneute Registrierung derselben URL aktualisiert den vorhandenen Client und belegt keinen weiteren Platz.
- **Mehrere Server.** Ein Push wird von einem einzelnen Queue-Worker verarbeitet. Wenn mehrere Webserver die Texte ausgeben, verwenden Sie über `ERECHT24_DISK` eine gemeinsame Disk (zum Beispiel S3).
- **Neue Umgebung oder neuer Speicher.** Gespeicherte Texte sind nicht Teil Ihres Repositorys. Führen Sie `php artisan erecht24:sync` nach dem ersten Deployment und nach jeder Änderung von Disk oder Verzeichnis aus.
