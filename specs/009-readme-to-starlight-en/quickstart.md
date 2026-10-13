# Quickstart: Verifying the Migration

```bash
cd docs
npm ci
npm run build        # parity + content checks, then astro build
npm run dev          # click through the sidebar at http://localhost:4321/en/
```

Repository checks (README must remain valid):

```bash
composer test && composer lint && composer analyse
```

Manual review:
1. Open each of the 16 pages; confirm content matches the README section and no "Coming soon" remains.
2. Confirm callouts: caution (cache, HTTPS), danger (unescaped HTML, push secret), tip (`QUEUE_CONNECTION=sync`).
3. Confirm Steps on Setup, Tabs on Facade and Inertia, hero + cards/steps on the landing page.
4. Confirm `git diff --stat` shows no changes in `README*.md`, `src/`, `docs/src/content/docs/de/`, or the three reserved pages.
