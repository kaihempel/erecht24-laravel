# Data Model: Starlight Docs Scaffold

## Documentation page
- **Location**: `docs/src/content/docs/<locale>/<group>/<slug>.(md|mdx)` or `<locale>/index.mdx`
- **Frontmatter** (required): `title` (string), `description` (string), `sidebar.order` (integer, unique within group)
- **Body**: "Coming soon" (en) / "Demnächst verfügbar" (de)
- **Rule**: every path exists in both locales with the same file name and extension.

## Navigation group
| Directory | EN label | DE label |
|---|---|---|
| getting-started | Getting started | Erste Schritte |
| guides | Guides | Anleitungen |
| usage | Usage | Verwendung |
| reference | Reference | Referenz |
| project | Project | Projekt |

## Page inventory (per locale)
- `index.mdx`
- getting-started: `requirements.md`, `installation.md`, `quick-start.md`
- guides: `setup.mdx`, `local-development.md`, `queue-and-sync.md`, `deployment.md`
- usage: `blade-components.md`, `facade-and-inertia.mdx`, `events.md`, `api-client-and-store.md`
- reference: `configuration.md`, `artisan-commands.md`, `push-endpoint.md`, `exceptions.md`
- project: `security.md`, `troubleshooting.md`, `changelog.mdx`

## Locale
`en` (default, label "English"), `de` (label "Deutsch", `lang: 'de'`).
