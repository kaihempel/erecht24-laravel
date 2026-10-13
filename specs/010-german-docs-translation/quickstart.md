# Quickstart: Verifying the German Translation

```bash
cd docs
npm ci
npm run build        # parity + content checks (EN and DE), then astro build
npm run dev          # click through http://localhost:4321/de/
```

Repository checks (README must remain valid):

```bash
composer test && composer lint && composer analyse
```

Manual review:
1. Open each of the 16 translated pages under `/de/`; confirm no "Demnächst verfügbar" and no "not translated" notice.
2. Use the language switcher on each page; it lands on the same page in English.
3. Confirm Steps on Setup, Tabs on Facade und Inertia, cards/steps on the landing page, and the same asides as English.
4. Confirm formal "Sie" and that code, commands, class names and `ERECHT24_*` names are unchanged.
5. Confirm `git diff --stat` shows no changes in `README*.md`, `src/`, `docs/src/content/docs/en/` or the three German placeholder pages.
