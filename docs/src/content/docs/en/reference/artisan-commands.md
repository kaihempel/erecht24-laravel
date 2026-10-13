---
title: "Artisan commands"
description: "Reference of the erecht24:register, status, unregister and sync commands."
sidebar:
  order: 2
---

| Command | Description |
|---|---|
| `erecht24:register {--push-uri=} {--write-env}` | Registers this environment as push client, or updates the client with the same (normalized) URL. Prints the client ID and the new secret, or writes `ERECHT24_PUSH_SECRET` into `.env` with `--write-env`. Refuses local/invalid URLs and a fourth client. |
| `erecht24:status {--test-push}` | Shows whether credentials are set (never their values), the languages, registered push clients and stored texts with their fetch time. `--test-push` fires a `ping` test push to the client matching the current push URL. |
| `erecht24:unregister {client-id?} {--force}` | Deletes a push client by ID, or the client matching the current push URL if no ID is given. Asks for confirmation unless `--force` is set. |
| `erecht24:sync {type?}` | Fetches one type (`imprint`, `privacyPolicy`, `privacyPolicySocialMedia`) or all types synchronously and stores them. Prints written and skipped languages; exits with `1` if a type failed. |

`register`, `unregister` and `sync` exit with `1` on errors. Every update of an existing client through `erecht24:register` issues a new secret.
