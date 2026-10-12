# Contract: Docs site & CI

## Commands (run in `docs/`)
| Command | Behaviour |
|---|---|
| `npm ci` | Clean, reproducible install from `package-lock.json` |
| `npm run dev` | Local preview server |
| `npm run build` | Static build into `docs/dist`; exit code 0 required |

## Routes
- `/en/` and `/de/` render the index; each page at `/<locale>/<group>/<slug>/`.

## CI workflow `.github/workflows/docs.yml`
- Triggers: `pull_request`, `push` (branch `main`); `paths`: `docs/**`, `CHANGELOG.md`, `.github/workflows/docs.yml`
- Steps: checkout → setup-node (LTS, npm cache) → `npm ci` → `npm run build` in `docs/`
- No deploy step, no secrets, `permissions: contents: read`.

## Repository invariants
- `git archive HEAD | tar -t` lists nothing under `docs/`.
- `lint.yml` and `tests.yml` unchanged.
