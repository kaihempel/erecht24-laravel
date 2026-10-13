---
title: "Lokale Entwicklung"
description: "Anforderungen an die Push-URL und wie Sie Pushes auf einem lokalen Rechner über einen Tunnel empfangen."
sidebar:
  order: 2
---

eRecht24 muss die Push-URL aus dem Internet erreichen können:

:::caution
Sie muss eine absolute `http(s)`-URL sein. Verwenden Sie HTTPS: Das Secret wird im Request-Body übertragen. (`erecht24:register` erzwingt das Schema nicht.)
:::

- `erecht24:register` lehnt Hosts ab, die von außen nicht erreichbar sein können: `localhost`, Hosts mit der Endung `.test`, `.local` oder `.localhost` sowie Loopback-, private oder reservierte IP-Adressen. Hostnamen werden nicht per DNS aufgelöst; ein öffentlich wirkender Name, der auf ein privates Netz zeigt, wird also nicht erkannt.
- Standardmäßig wird die URL aus `APP_URL` und `ERECHT24_PUSH_PATH` gebildet. Mit `--push-uri=` registrieren Sie eine andere URL.

**Lokale Entwicklung:** Machen Sie Ihre lokale Anwendung über einen Tunnel wie [ngrok](https://ngrok.com), [Expose](https://expose.dev) oder `cloudflared` erreichbar und registrieren Sie die Tunnel-URL:

```bash
php artisan erecht24:register --push-uri=https://abc123.ngrok-free.app/api/erecht24/push
```

`erecht24:status --test-push` und `erecht24:unregister` (ohne ID) suchen den Client anhand von `APP_URL` + `ERECHT24_PUSH_PATH`. Wenn Sie eine URL mit `--push-uri` registriert haben, setzen Sie `APP_URL` auf dieselbe Basis-URL, damit diese Befehle den Client finden, oder übergeben Sie `erecht24:unregister` die Client-ID.

Zum lokalen Ausprobieren brauchen Sie keinen Push: `php artisan erecht24:sync` holt die Texte bei Bedarf.
