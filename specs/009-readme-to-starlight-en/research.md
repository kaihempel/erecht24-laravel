# Research: README to Starlight (EN)

## D1: Parity check vs. `.mdx` renames
- **Finding**: `docs/scripts/check-parity.mjs` compares EN and DE relative file names including extension and runs before `astro build`. Renaming EN `index.md`, `guides/setup.md`, `usage/facade-and-inertia.md` to `.mdx` would fail the build with "missing in de".
- **Decision**: Normalize extensions (`.md`/`.mdx` → stem) in the parity check. Do not rename DE files (spec FR-014).
- **Alternatives**: Rename DE files too (violates scope; DE migration is a separate issue). Keep `.md` and skip components (violates acceptance criteria for `<Steps>`/`<Tabs>`).

## D2: Verifying content acceptance criteria
- **Decision**: Add `docs/scripts/check-content.mjs` (no new dependencies) and chain it into the `check` npm script. It checks for each EN page: frontmatter `title` and `description`; no `# ` heading outside fenced code; no `README` links; that `reference/configuration` contains each `ERECHT24_*` variable from `config/erecht24.php` in backticks; that `setup.mdx` imports `Steps` and `facade-and-inertia.mdx` imports `Tabs`; internal links start with `/en/`.
- **Rationale**: Gives CI-enforced coverage of FR-009/010/012 without a heavy tool. Pest cannot meaningfully test Astro content.
- **Alternatives**: Manual review only (not repeatable); a Pest test reading docs files (couples PHP suite to docs, but is viable as a fallback).

## D3: Broken-link detection
- **Finding**: Astro/Starlight does not fail the build on broken internal links by default.
- **Decision**: Check internal links in `check-content.mjs`: every `](/en/...)` target must map to an existing EN page file (`<path>.md|.mdx` or `index`), including anchors being ignored. Optionally add `starlight-links-validator` later; not adding a dependency now.
- **Alternatives**: `starlight-links-validator` plugin (more thorough, adds dependency — recorded as a follow-up).

## D4: README anchor rewriting
- **Decision**: Map README anchors to site paths: `#requirements`→`/en/getting-started/requirements/`, `#installation`→`/en/getting-started/installation/`, `#quick-start`→`/en/getting-started/quick-start/`, `#setup-step-by-step`→`/en/guides/setup/`, `#push-url-requirements`→`/en/guides/local-development/`, `#queue-worker-and-initial-sync`→`/en/guides/queue-and-sync/`, `#configuration`/`#env-reference`→`/en/reference/configuration/`, `#push-endpoint`→`/en/reference/push-endpoint/`, `#artisan-commands`→`/en/reference/artisan-commands/`, `#deployment-checklist-and-pitfalls`→`/en/guides/deployment/`, `#security`→`/en/project/security/`, usage subsections→their `/en/usage/...` pages. Links to the Development section or `README.de.md` are dropped or rephrased without a link.

## D5: Callout mapping
- **Decision**: `:::caution` for config/route cache and public HTTPS; `:::danger` for trusted unescaped HTML and push secret handling; `:::tip` for `QUEUE_CONNECTION=sync` on small sites. Pitfalls on the deployment page keep their bold lead-ins; the config-cache, route-cache and public-HTTPS pitfalls are wrapped in `:::caution`. README text inside asides is unchanged.

## D6: MDX hazards
- **Decision**: Keep code in fenced blocks; in prose outside code, escape or backtick `{`, `}` and bare `<`. Component imports go below the frontmatter (`import { Steps } from '@astrojs/starlight/components';`). `:::` asides work in both `.md` and `.mdx`.

## D7: Deployment pitfalls (from clarification)
- **Decision**: Whole README section "Deployment checklist and pitfalls" lives on `guides/deployment`.

## D8: Agent context update
- `.specify/scripts/bash/update-agent-context.sh` does not exist in this repo; CLAUDE.md is maintained manually. Add a short note on the new docs check script to CLAUDE.md's Documentation section during implementation.
