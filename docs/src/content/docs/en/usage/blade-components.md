---
title: "Blade components"
description: "Render the imprint, privacy policy and social-media privacy policy with the package's Blade components, and customize their views."
sidebar:
  order: 1
---

```blade
<x-erecht24::imprint />
<x-erecht24::privacy-policy lang="en" class="prose" />
<x-erecht24::privacy-policy-social-media />

{{-- generic form --}}
<x-erecht24::legal-text type="imprint" lang="de" />
```

- `type` (generic component only): `imprint`, `privacyPolicy`, `privacyPolicySocialMedia`, or the slugs `privacy-policy` and `privacy-policy-social-media`. Any other value throws an `\InvalidArgumentException` that lists the allowed values.
- `lang` (optional): `de` or `en`. Regional forms such as `de_DE` or `en-US` are reduced to the primary language.
- Additional attributes such as `class` are added to the wrapper `<div>`, which also carries a `lang` attribute with the language actually rendered.
- Rendering reads only the stored file; it never calls the API.

**Language resolution:** the `lang` attribute, then the application locale (`app()->getLocale()`), then the first language in `ERECHT24_TEXT_LANGUAGES`. Only configured languages are considered. If none of them has stored content, the next configured language that has content is used.

**Missing text:** if no text is stored for the type, the `erecht24::missing` view renders a short neutral message ("This legal text is currently not available.") instead of throwing. With `APP_DEBUG=true` it additionally shows the hint to run `php artisan erecht24:sync`. Read errors are logged and treated as missing.

## Trust seals

The package can show the eRecht24 trust seal centered below the imprint and the privacy policy, inside the wrapper `<div>`. This applies to `<x-erecht24::imprint>`, `<x-erecht24::privacy-policy>` and the generic `legal-text` component for those two types. The social-media privacy policy and the `missing` placeholder never show a seal. Nothing is shown by default: without seal configuration the output is unchanged.

Choose a variant per text in `.env` (see [Configuration](/en/reference/configuration/)):

```dotenv
ERECHT24_IMPRINT_SEAL=light-horizontal
ERECHT24_PRIVACY_POLICY_SEAL=dark-vertical
```

Allowed values are `light-horizontal`, `light-vertical`, `dark-horizontal` and `dark-vertical`. Leave a variable unset or empty to show no seal for that text.

The seal images are not served from the package directory. Publish them to `public/vendor/erecht24/` after installation and as part of every deployment:

```bash
php artisan vendor:publish --tag=erecht24-assets
```

After a package update, add `--force` to overwrite the previously published images:

```bash
php artisan vendor:publish --tag=erecht24-assets --force
```

:::caution
The image URL (`vendor/erecht24/impressum-siegel-<variant>-large.webp` or `vendor/erecht24/datenschutz-siegel-<variant>-large.webp`) is emitted even if the file was never published. Skipping the publish step therefore results in a broken image on your legal pages.
:::

- The seal images contain German text only, also on English pages. The `alt` text follows the rendered language (translation keys `erecht24::seal.imprint` and `erecht24::seal.privacy_policy`).
- The seal is displayed 300 px wide (horizontal variants) or 180 px wide (vertical variants), with `width`/`height` attributes and `loading="lazy"`. No inline styles are used, so a strict Content Security Policy works.
- An invalid value makes `Erecht24Settings` throw an `InvalidConfigurationException`. The components log it as a warning and render the text without seal.
- To change the markup, publish the views with the `erecht24-views` tag (see below) and edit `components/legal-text`. The seal is passed in as `$seal`. If you published the views before seals existed, re-publish them or pass `seal` through in your copies; otherwise no seal is shown.

## Customizing the views

```bash
php artisan vendor:publish --tag=erecht24-views
```

The views are copied to `resources/views/vendor/erecht24/` and take precedence over the package views: `components/legal-text`, `imprint`, `privacy-policy`, `privacy-policy-social-media` and `missing`. The content views receive `$content`, `$type` (a `LegalTextType`), `$lang` and, optionally, `$seal` (a `LegalTextSeal` or `null`; views that ignore it keep working). The English message in `missing` can be translated there.

:::danger
Keep `{!! $content !!}` unescaped, otherwise the HTML is shown as text. The texts are trusted HTML from the eRecht24 API; never route user-supplied content through these views.
:::
