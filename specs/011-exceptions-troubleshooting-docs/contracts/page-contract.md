# Page Contract: exceptions, troubleshooting, deployment

Enforced by `docs/scripts/check-content.mjs` (both locales) on top of the existing 009/010 contract (frontmatter, no H1, `/<locale>/` links and anchors, EN/DE shape parity).

## reference/exceptions

- No placeholder text; `title` and `description` set.
- One H2 for each class file found in `src/Exceptions/` (name in backticks, e.g. `## \`LocalPushUriException\``), derived from the directory so a new exception fails the build until documented.
- Every class section contains the bold labels **Extends**, **Thrown when** and **Handling** in that order (DE: **Erbt von**, **Wird geworfen, wenn**, **Behandlung**).
- Intro states that exception messages never contain the API key, plugin key or push secret.

## project/troubleshooting

- No placeholder text; at least 9 H2 entries.
- Every H2 section contains the three bold labels in order: `**Symptom:**` → `**Cause:**` → `**Fix:**` (DE: `**Symptom:**` → `**Ursache:**` → `**Lösung:**`).
- Required symptoms (checked in review, not by script): 403, 503, 404 after path change, no updates (queue), URL refused, fourth client refused, "currently not available", texts differ between servers, push URL unreachable.

## guides/deployment

- Keeps the checklist; contains a link to `/<locale>/project/troubleshooting/`; the long "Pitfalls" caution blocks are removed.
