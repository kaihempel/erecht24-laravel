---
title: "Fehlerbehebung"
description: "Symptome, Ursachen und Lösungen für häufige Probleme mit Push-Endpunkt, Registrierung, Queue und gespeicherten Rechtstexten."
sidebar:
  order: 2
---

Suchen Sie Ihr Symptom, prüfen Sie die Ursache und wenden Sie die Lösung an. Informationen zu den Exception-Klassen und ihrer Behandlung finden Sie in der [Exceptions-Referenz](/de/reference/exceptions/).

## Jeder Push liefert 403

**Symptom:** Ihr Endpunkt antwortet auf Pushes von eRecht24 mit `403`, oder `php artisan erecht24:status --test-push` schlägt fehl.

**Ursache:** Das von eRecht24 gesendete Secret stimmt nicht mit `ERECHT24_PUSH_SECRET` überein. Meist ist die Konfiguration gecacht und enthält noch ein altes Secret: Bei gecachter Konfiguration wirken Änderungen an der `.env` (auch das von `erecht24:register --write-env` geschriebene Secret) erst, nachdem der Cache neu aufgebaut wurde. Eine weitere Ursache ist, dass das Secret neu ausgestellt wurde, weil jede Aktualisierung eines vorhandenen Clients über `erecht24:register` ein neues Secret erzeugt, und der neue Wert nicht in diese Umgebung übernommen wurde.

**Lösung:** Tragen Sie das aktuelle Secret in die `.env` ein und führen Sie dann `php artisan config:cache` aus. Prüfen Sie mit `php artisan erecht24:status --test-push`. Wenn unklar ist, welches Secret aktuell ist, führen Sie `php artisan erecht24:register` erneut aus und verwenden Sie das neu ausgegebene Secret. Siehe den [Push-Endpunkt](/de/reference/push-endpoint/) und die [Artisan-Befehle](/de/reference/artisan-commands/).

## Push liefert 503

**Symptom:** Der Push-Endpunkt antwortet mit `503`.

**Ursache:** `ERECHT24_PUSH_SECRET` ist nicht konfiguriert. Der Endpunkt akzeptiert niemals Pushes ohne Secret; ein konfiguriertes Secret wird mit `hash_equals` verglichen.

**Lösung:** Führen Sie `php artisan erecht24:register` in dieser Umgebung aus, setzen Sie das ausgegebene Secret als `ERECHT24_PUSH_SECRET` (oder verwenden Sie `--write-env`) und führen Sie `php artisan config:cache` aus. Siehe die [Konfigurationsreferenz](/de/reference/configuration/).

## Push-Route liefert nach Änderung des Push-Pfads 404

**Symptom:** Nach einer Änderung von `ERECHT24_PUSH_PATH` oder `ERECHT24_PUSH_ENABLED` antwortet die Push-URL mit `404`.

**Ursache:** Sind die Routen gecacht, registriert das Paket die Push-Route nicht selbst; sie ist Teil des Route-Caches, der noch den alten Pfad oder gar keine Route enthält. `ERECHT24_PUSH_ENABLED=false` entfernt die Route ebenfalls.

**Lösung:** Stellen Sie sicher, dass `ERECHT24_PUSH_ENABLED` nicht `false` ist, und führen Sie `php artisan route:cache` erneut aus. Prüfen Sie das Ergebnis mit `php artisan route:list --name=erecht24`. Hat sich der Pfad geändert, führen Sie `php artisan erecht24:register` aus, damit eRecht24 die neue URL kennt.

## Pushes werden angenommen, aber Texte aktualisieren sich nie

**Symptom:** Der Push-Endpunkt antwortet mit `200` und `queued`, aber die gespeicherten Texte bleiben unverändert.

**Ursache:** Der Endpunkt stellt nur einen `SyncLegalTextJob` in die Queue; die Texte werden vom Queue-Worker abgerufen. Es läuft kein Worker, oder er führt nach einem Deployment noch alten Code aus.

**Lösung:** Betreiben Sie `php artisan queue:work` unter einem Prozess-Manager und starten Sie den Worker nach jedem Deployment mit `php artisan queue:restart` neu. Für kleine Websites setzen Sie `QUEUE_CONNECTION=sync`. Um die Verbindung zu eRecht24 unabhängig von der Queue zu prüfen, führen Sie `php artisan erecht24:sync` aus. Siehe [Queue und Sync](/de/guides/queue-and-sync/).

## `erecht24:register` lehnt die URL ab

**Symptom:** `php artisan erecht24:register` bricht mit einer Meldung ab, dass die Push-URI nicht registriert wird.

**Ursache:** Die Push-URL (`APP_URL` plus `ERECHT24_PUSH_PATH`) zeigt auf einen lokalen oder privaten Host wie `localhost` oder eine private IP-Adresse, oder sie ist keine absolute `http(s)`-URL mit Host. eRecht24 kann solche Adressen nicht erreichen. Das Paket wirft `LocalPushUriException` (siehe die [Exceptions-Referenz](/de/reference/exceptions/)).

**Lösung:** Setzen Sie `APP_URL` auf die öffentliche HTTPS-URL der Umgebung. Verwenden Sie für die lokale Entwicklung einen öffentlichen Tunnel (zum Beispiel ngrok oder Expose) und übergeben Sie ihn explizit: `php artisan erecht24:register --push-uri=https://example.test/api/erecht24/push`.

## `erecht24:register` lehnt einen vierten Client ab

**Symptom:** `php artisan erecht24:register` verweigert die Registrierung und listet vorhandene Clients mit ihren IDs auf.

**Ursache:** eRecht24 erlaubt höchstens drei Push-Clients pro Projekt, zum Beispiel lokal (Tunnel), Staging und Produktion. Das Paket wirft `TooManyPushClientsException`.

**Lösung:** Geben Sie mit `php artisan erecht24:unregister <client-id>` einen Platz frei und registrieren Sie erneut. Eine erneute Registrierung derselben URL aktualisiert den vorhandenen Client und belegt keinen weiteren Platz.

## „This legal text is currently not available.“

**Symptom:** Eine Blade-Komponente rendert „This legal text is currently not available.“ statt des Textes.

**Ursache:** Für den angeforderten Typ ist kein Text gespeichert. Das passiert vor dem ersten Sync, in einer neuen Umgebung oder wenn sich `ERECHT24_DISK` oder das Speicherverzeichnis geändert hat: Gespeicherte Texte sind nicht Teil Ihres Repositorys.

**Lösung:** Führen Sie `php artisan erecht24:sync` aus und prüfen Sie das Ergebnis mit `php artisan erecht24:status`. Mit `APP_DEBUG=true` zeigt der Platzhalter zusätzlich einen Entwicklerhinweis. Siehe [Queue und Sync](/de/guides/queue-and-sync/).

## Texte unterscheiden sich zwischen Webservern

**Symptom:** Verschiedene Webserver geben unterschiedliche oder fehlende Rechtstexte aus.

**Ursache:** Jeder Server verwendet seine eigene lokale Disk. Ein Push wird von einem Queue-Worker verarbeitet, sodass nur die Disk dieses Servers aktualisiert wird.

**Lösung:** Verwenden Sie auf allen Servern über `ERECHT24_DISK` eine gemeinsame Disk, zum Beispiel S3, und führen Sie anschließend einmal `php artisan erecht24:sync` aus. Siehe die [Konfigurationsreferenz](/de/reference/configuration/).

## eRecht24 erreicht die Push-URL nicht

**Symptom:** `php artisan erecht24:status --test-push` schlägt fehl, oder eRecht24 liefert keine Pushes aus, obwohl Secret und Routen korrekt sind.

**Ursache:** Die Umgebung ist aus dem öffentlichen Internet nicht erreichbar, zum Beispiel Staging hinter HTTP-Basic-Auth, VPN oder IP-Allowlist.

**Lösung:** Nehmen Sie den Push-Pfad vom Schutz aus oder verwenden Sie für Push eine andere Umgebung. Die URL muss öffentliches HTTPS sein. Prüfen Sie mit `php artisan erecht24:status --test-push`.
