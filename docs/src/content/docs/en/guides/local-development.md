---
title: "Local development"
description: "Push URL requirements and how to receive pushes on a local machine with a tunnel."
sidebar:
  order: 2
---

eRecht24 must be able to reach the push URL from the internet:

:::caution
It must be an absolute `http(s)` URL. Use HTTPS: the secret is sent in the request body. (`erecht24:register` does not enforce the scheme.)
:::

- `erecht24:register` refuses hosts that cannot be reachable from outside: `localhost`, hosts ending in `.test`, `.local` or `.localhost`, and loopback, private or reserved IP addresses. Host names are not resolved via DNS, so a public-looking name that points to a private network is not detected.
- By default the URL is built from `APP_URL` and `ERECHT24_PUSH_PATH`. Pass `--push-uri=` to register a different URL.

**Local development:** expose your local application through a tunnel such as [ngrok](https://ngrok.com), [Expose](https://expose.dev) or `cloudflared`, and register the tunnel URL:

```bash
php artisan erecht24:register --push-uri=https://abc123.ngrok-free.app/api/erecht24/push
```

`erecht24:status --test-push` and `erecht24:unregister` (without an ID) look up the client by `APP_URL` + `ERECHT24_PUSH_PATH`. If you registered a URL with `--push-uri`, set `APP_URL` to the same base URL so these commands find it, or pass the client ID to `erecht24:unregister`.

You do not need push to try the package locally: `php artisan erecht24:sync` fetches the texts on demand.
