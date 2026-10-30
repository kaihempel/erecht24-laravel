# Spec 014: Trust seal documentation (#62)

Users learn from the EN and DE docs how to enable trust seals and that the seal images must be published with `erecht24-assets` in every deployment. Docs, changelog and `CLAUDE.md` only; no package API change.

## Requirements

- Configuration pages (EN/DE): `ERECHT24_IMPRINT_SEAL` / `ERECHT24_PRIVACY_POLICY_SEAL` with default "–", the four allowed values, empty means no seal, invalid values throw `InvalidConfigurationException`.
- Blade components page: H2 "Trust seals" / "Vertrauenssiegel" covering placement and types, `.env` example, `vendor:publish --tag=erecht24-assets` (with `--force` after updates), German-only image text, broken image when not published, customizing via `erecht24-views` (`components/legal-text`, `$seal`). Same headings, asides and code examples in EN and DE.
- Deployment guide and installation page (EN/DE) name the publish step.
- Exceptions reference lists the seal keys under `InvalidConfigurationException`.
- `CHANGELOG.md` "Unreleased" → "Added": trust seals, `erecht24-assets` tag, env variables; no breaking change, nothing shown by default.
- `CLAUDE.md` describes `SealVariant`, `SealResolver`, `erecht24-assets` and `$seal`.

## Acceptance

- `DocsEnvReferenceTest` and `cd docs && npm run build` (incl. `npm run check`) pass.
- No regressions in `composer test`, `composer lint`, `composer analyse`.
