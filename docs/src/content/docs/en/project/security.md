---
title: "Security"
description: "How the package protects the push secret and credentials, and what you must keep in mind when handling trusted HTML."
sidebar:
  order: 1
---

## Security

:::danger
- **Push secret.** Treat `ERECHT24_PUSH_SECRET` like a password. Keep it in `.env` or your secret manager and never commit it. Every environment gets its own secret through its own registration.
- **Rotation.** Run `php artisan erecht24:register` (with the same push URL) again. eRecht24 issues a new secret and the old one stops working immediately; update `.env` and run `php artisan config:cache` right away.
:::

- **Verification.** The endpoint compares the secret in constant time with `hash_equals` and answers `503` when no secret is configured, so the endpoint never accepts pushes without a secret.
- **No secrets in logs or output.** Neither the push secret nor the push payload is logged. Exception messages never contain the API key, plugin key or secret. `erecht24:status` reports only whether credentials are set, and push URLs are shown without user info or query string. `erecht24:register` prints the secret once, by design.

:::danger
- **Trusted HTML.** The texts are HTML delivered by the eRecht24 API and are output unescaped (`{!! !!}`) and unsanitized on purpose. Never route user-supplied content through these views or the facade output.
:::

- **No executable storage.** Texts are stored only as `.html` files plus `.meta.json` metadata, never as `.php` or `.blade.php`, so stored content is never executed or compiled as a template.

## Disclaimer

This package is not affiliated with, endorsed by, or sponsored by eRecht24. eRecht24 is a trademark of its respective owner.

This package is technical integration software and not legal advice. You are responsible for the content of your legal texts and for your eRecht24 account and API setup.
