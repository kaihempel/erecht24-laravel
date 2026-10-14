# Quickstart: Validating the Exceptions and Troubleshooting Pages

## Prerequisites

Node 22.12+; `cd docs && npm ci`.

## Validate

1. `cd docs && npm run check` – parity and content contract (both locales). Expected: `docs content ok`.
2. `cd docs && npm run build` – full build. Expected: exit 0, pages `/en/reference/exceptions/`, `/de/reference/exceptions/`, `/en/project/troubleshooting/`, `/de/project/troubleshooting/` generated.
3. `cd docs && npm run dev` – open both pages in EN and DE, confirm: eight exception sections, nine Symptom/Cause/Fix entries, no "Coming soon", deployment page links to troubleshooting.
4. Spot-check facts: for each exception, compare "Extends" and "Thrown when" with `src/Exceptions/*.php` and the call sites listed in [research.md](research.md).
5. Repo gates unchanged: `composer test`, `composer lint`, `composer analyse`.

## Negative check (test-first)

Before writing the pages, run `npm run check` after the script change: it must fail for the four placeholder pages in EN and DE.
