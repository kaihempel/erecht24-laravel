---
title: "Push endpoint"
description: "How the push endpoint responds to eRecht24 requests, and how it is secured and rate limited."
sidebar:
  order: 3
---

The package registers `POST {ERECHT24_PUSH_PATH}` (default `/api/erecht24/push`) with the route name `erecht24.push`. eRecht24 sends `erecht24_secret` and `erecht24_type` (form-encoded or JSON).

| Request | Response |
|---|---|
| no `ERECHT24_PUSH_SECRET` configured | `503` |
| missing or wrong secret | `403` |
| `erecht24_type=ping` | `200 {"code":200,"message":"pong"}` |
| `imprint`, `privacyPolicy`, `privacyPolicySocialMedia` | `200 {"code":200,"message":"queued"}`, dispatches `SyncLegalTextJob` |
| any other type | `422` |

The secret is checked first (with `hash_equals`), so unauthenticated callers learn nothing about the payload. The route is outside the `web` middleware group (no session, cookies or CSRF token) and limited to `ERECHT24_PUSH_RATE_LIMIT` requests per minute per IP through the `erecht24-push` rate limiter. It is not registered when `ERECHT24_PUSH_ENABLED=false`.
