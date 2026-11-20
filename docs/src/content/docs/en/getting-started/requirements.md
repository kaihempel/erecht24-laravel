---
title: "Requirements"
description: "What you need before installing erecht24-laravel."
sidebar:
  order: 1
---

- PHP 8.3 or newer
- Laravel 12 or 13
- An eRecht24 account with a project in the eRecht24 Projekt Manager and its API key
- A URL for your application that is reachable over the public internet (for push notifications)
- A queue worker, or `QUEUE_CONNECTION=sync`
- A filesystem disk for the stored texts (the default `local` disk works for a single server)

:::note
Since version 1.1, the package ships the `PLUGIN_KEY` itself. You no longer need to request it from eRecht24; you only need your API key. Set `ERECHT24_PLUGIN_KEY` only if you want to override the shipped key.
:::
