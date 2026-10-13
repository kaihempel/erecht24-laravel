# Data Model: README to Starlight (EN)

No persisted data. Content entities:

## Docs page
- **path**: `docs/src/content/docs/en/<section>/<slug>.md|mdx`
- **frontmatter**: `title` (required), `description` (required), `sidebar.order` (retain from scaffold)
- **body**: H2/H3 only; no H1; internal links `/en/<section>/<slug>/`
- **rule**: extension `.mdx` only when the page imports Starlight components

## README section → page mapping (each README section on exactly one page)

| README section | Page |
|---|---|
| Intro + 4-step flow | `index.mdx` |
| Requirements | `getting-started/requirements.md` |
| Installation | `getting-started/installation.md` |
| Quick start | `getting-started/quick-start.md` |
| Setup step by step | `guides/setup.mdx` |
| Push URL requirements | `guides/local-development.md` |
| Queue worker and initial sync | `guides/queue-and-sync.md` |
| Deployment checklist and pitfalls (whole) | `guides/deployment.md` |
| Blade components + Customizing the views | `usage/blade-components.md` |
| Facade and Inertia | `usage/facade-and-inertia.mdx` |
| Reacting to updates: `LegalTextUpdated` | `usage/events.md` |
| Using the API client and the store directly | `usage/api-client-and-store.md` |
| Configuration (`.env` reference + non-env settings) | `reference/configuration.md` |
| Artisan commands | `reference/artisan-commands.md` |
| Push endpoint | `reference/push-endpoint.md` |
| Security + Disclaimer | `project/security.md` |
| Contents, Development, License | not migrated |

## Callout
- **type**: `caution` | `danger` | `tip`, see research D5.
