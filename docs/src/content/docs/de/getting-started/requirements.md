---
title: "Voraussetzungen"
description: "Was Sie benötigen, bevor Sie erecht24-laravel installieren."
sidebar:
  order: 1
---

- PHP 8.3 oder neuer
- Laravel 12 oder 13
- Ein eRecht24-Konto mit einem Projekt im eRecht24 Projekt Manager, dessen API-Schlüssel und ein Plugin-Schlüssel
- Eine URL Ihrer Anwendung, die aus dem öffentlichen Internet erreichbar ist (für Push-Benachrichtigungen)
- Ein Queue-Worker oder `QUEUE_CONNECTION=sync`
- Eine Filesystem-Disk für die gespeicherten Texte (die Standard-Disk `local` genügt für einen einzelnen Server)
