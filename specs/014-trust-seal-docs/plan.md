# Plan: Trust seal documentation

1. Refine the seal rows on `reference/configuration.md` (EN/DE).
2. Rewrite the seal section of `usage/blade-components.md` (EN/DE) as "Trust seals" / "Vertrauenssiegel" with `.env` and publish examples and one caution aside.
3. Add the publish step to `guides/deployment.md` and `getting-started/installation.md` (EN/DE); mention seal keys in `reference/exceptions.md`.
4. Update `CHANGELOG.md` and `CLAUDE.md`.
5. Run `cd docs && npm run build`, `composer test`, `composer lint`, `composer analyse`.
