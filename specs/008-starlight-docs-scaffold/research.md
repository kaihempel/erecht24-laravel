# Research: Starlight Docs Scaffold

## Decision 1: Framework versions
- **Decision**: Use the official Starlight template (`npm create astro@latest docs -- --template starlight`), keeping the versions it resolves (currently `astro` 7.x, `@astrojs/starlight` 0.42.x) and committing the lock file.
- **Rationale**: Matches the issue; lock file gives reproducible `npm ci`.
- **Alternatives**: Docusaurus/VitePress/MkDocs: rejected, the issue prescribes Starlight and it has first-class i18n.

## Decision 2: Node version in CI
- **Decision**: `actions/setup-node` with `node-version: 'lts/*'`, npm cache keyed on `docs/package-lock.json`. The `engines.node >=22.12` constraint is inherited from Astro; no `.nvmrc` required.
- **Rationale**: Issue says "Node LTS"; the current LTS satisfies Astro's >=22.12 requirement.
- **Alternatives**: Pinning `22`: more deterministic but needs manual bumps.

## Decision 3: i18n layout
- **Decision**: `defaultLocale: 'en'` with `locales: { en, de }` (both prefixed, `/en/`, `/de/`); content in `src/content/docs/en` and `de`. Root `/` has no page, so add an explicit Astro `redirects` entry `'/': '/en/'` (FR-012).
- **Rationale**: Issue acceptance criteria require `/en/` and `/de/`.
- **Alternatives**: `root` locale for English: contradicts `/en/` criterion.

## Decision 4: Sidebar
- **Decision**: Five groups with `autogenerate: { directory }` and `translations: { de }`; page order via `sidebar.order` frontmatter.
- **Rationale**: Adding later content needs no config change.

## Decision 5: CI triggers
- **Decision**: `pull_request` and `push` to `main`, both with `paths: ['docs/**', 'CHANGELOG.md', '.github/workflows/docs.yml']`, `working-directory: docs`, `permissions: contents: read`, `persist-credentials: false` (matches existing workflows).
- **Rationale**: Includes the workflow file itself so edits to it are validated (small addition to the issue's list).

## Decision 6: Template cleanup
- **Decision**: Delete template example pages (guides/example, reference/example), `src/assets/*`, and unused public assets; keep `content.config.ts`, `tsconfig.json`, favicon if used by config.
- **Rationale**: FR-007.

## Decision 7: Site URL
- **Decision**: `site: 'https://example.invalid'` with a `// TODO(#11)` comment; a syntactically valid URL keeps the build working.
- **Rationale**: FR-011; `site` must be a valid URL for sitemap generation.

## Decision 8: Equivalence check
- **Decision**: Verify EN/DE parity with a simple `diff <(cd en && find . | sort) <(cd de && find . | sort)` documented in quickstart; no automated test now.
- **Rationale**: Keeps scope to the skeleton; automation can follow with content issues.
