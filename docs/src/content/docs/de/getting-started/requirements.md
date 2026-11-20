---
title: "Voraussetzungen"
description: "Was Sie benötigen, bevor Sie erecht24-laravel installieren."
sidebar:
  order: 1
---

- PHP 8.3 oder neuer
- Laravel 12 oder 13
- Ein eRecht24-Konto mit einem Projekt im eRecht24 Projekt Manager und dessen API-Schlüssel
- Eine URL Ihrer Anwendung, die aus dem öffentlichen Internet erreichbar ist (für Push-Benachrichtigungen)
- Ein Queue-Worker oder `QUEUE_CONNECTION=sync`
- Eine Filesystem-Disk für die gespeicherten Texte (die Standard-Disk `local` genügt für einen einzelnen Server)

:::note
Ab Version 1.1 liefert das Paket den `PLUGIN_KEY` selbst mit. Sie müssen ihn nicht mehr bei eRecht24 beantragen; Sie benötigen nur Ihren API-Schlüssel. Setzen Sie `ERECHT24_PLUGIN_KEY` nur, wenn Sie den mitgelieferten Schlüssel überschreiben möchten.
:::
