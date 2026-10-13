---
title: "Push-Endpunkt"
description: "Wie der Push-Endpunkt auf eRecht24-Anfragen antwortet und wie er abgesichert und begrenzt ist."
sidebar:
  order: 3
---

Das Paket registriert `POST {ERECHT24_PUSH_PATH}` (Standard `/api/erecht24/push`) mit dem Routennamen `erecht24.push`. eRecht24 sendet `erecht24_secret` und `erecht24_type` (formularkodiert oder als JSON).

| Anfrage | Antwort |
|---|---|
| kein `ERECHT24_PUSH_SECRET` konfiguriert | `503` |
| Secret fehlt oder ist falsch | `403` |
| `erecht24_type=ping` | `200 {"code":200,"message":"pong"}` |
| `imprint`, `privacyPolicy`, `privacyPolicySocialMedia` | `200 {"code":200,"message":"queued"}`, stellt `SyncLegalTextJob` in die Queue |
| jeder andere Typ | `422` |

Das Secret wird zuerst geprüft (mit `hash_equals`), sodass nicht authentifizierte Aufrufer nichts über den Payload erfahren. Die Route liegt außerhalb der Middleware-Gruppe `web` (keine Session, keine Cookies, kein CSRF-Token) und ist über den Rate-Limiter `erecht24-push` auf `ERECHT24_PUSH_RATE_LIMIT` Anfragen pro Minute und IP begrenzt. Mit `ERECHT24_PUSH_ENABLED=false` wird sie nicht registriert.
