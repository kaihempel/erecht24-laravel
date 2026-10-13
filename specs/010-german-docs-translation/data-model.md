# Data Model: German Documentation Pages

- **Page** (stem = path without extension, e.g. `guides/setup`)
  - `locale`: `en` | `de`
  - `title`, `description`: non-empty for non-reserved pages
  - `headings[]`: ordered list of (level, text); German count per level equals English
  - `components[]`: `Steps`, `Tabs`, `CardGrid` imported and used where English uses them
  - `links[]`: internal targets starting with the locale prefix (`/de/...`), each resolving to an existing page stem
- **Page pair**: `en/<stem>` and `de/<stem>`; extensions may differ (`.md` vs `.mdx`).
- **Reserved page**: `project/changelog`, `reference/exceptions`, `project/troubleshooting`; German content is a placeholder, exempt from content rules.
- **Env variable reference**: set of `ERECHT24_*` names in `config/erecht24.php`; each must appear in backticks on `reference/configuration` in both locales.

Section-to-page source map (`README.de.md` → German page):

| README.de.md section | German page |
|---|---|
| Voraussetzungen | `getting-started/requirements` |
| Installation | `getting-started/installation` |
| Schnellstart | `getting-started/quick-start` |
| Konfiguration (`.env`-Referenz, Einstellungen ohne Umgebungsvariable) | `reference/configuration` |
| Einrichtung Schritt für Schritt | `guides/setup` |
| Anforderungen an die Push-URL | `guides/local-development` |
| Queue-Worker und erster Sync | `guides/queue-and-sync` |
| Verwendung › Blade-Komponenten, Views anpassen | `usage/blade-components` |
| Verwendung › Facade und Inertia | `usage/facade-and-inertia` |
| Verwendung › LegalTextUpdated | `usage/events` |
| Verwendung › API-Client und Speicher | `usage/api-client-and-store` |
| Push-Endpunkt | `reference/push-endpoint` |
| Artisan-Befehle | `reference/artisan-commands` |
| Deployment-Checkliste und Stolperfallen | `guides/deployment` |
| Sicherheit | `project/security` |
| Einleitung (top of file) | `index` (plus English page for hero/cards) |
