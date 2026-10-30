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

## Trust seal

If you set `ERECHT24_IMPRINT_SEAL` and/or `ERECHT24_PRIVACY_POLICY_SEAL` (see [Configuration](/en/reference/configuration/)), the imprint and privacy policy components render the eRecht24 seal centered directly below the text, inside the wrapper `<div>`. The generic `legal-text` component behaves the same for those two types.

- The image is served from `vendor/erecht24/` (`impressum-siegel-<variant>-large.webp` or `datenschutz-siegel-<variant>-large.webp`); publish them with `php artisan vendor:publish --tag=erecht24-assets` (copies to `public/vendor/erecht24/`). The URL is emitted even if the file is missing.
- It is displayed 300 px wide (horizontal variants) or 180 px wide (vertical variants), with `width`/`height` attributes and `loading="lazy"`. No inline styles are used, so a strict Content Security Policy works.
- The `alt` text is German or English, matching the rendered language (translation keys `erecht24::seal.imprint` and `erecht24::seal.privacy_policy`).
- An invalid seal setting is logged as a warning and the text renders without seal.
- If you published the views earlier, re-publish them or pass `seal` through in your copies; otherwise no seal is shown.
- The social-media privacy policy and the `missing` placeholder never show a seal. Without seal configuration the output is unchanged.

## Customizing the views

```bash
php artisan vendor:publish --tag=erecht24-views
```

The views are copied to `resources/views/vendor/erecht24/` and take precedence over the package views: `components/legal-text`, `imprint`, `privacy-policy`, `privacy-policy-social-media` and `missing`. The content views receive `$content`, `$type` (a `LegalTextType`), `$lang` and, optionally, `$seal` (a `LegalTextSeal` or `null`; views that ignore it keep working). The English message in `missing` can be translated there.

:::danger
Keep `{!! $content !!}` unescaped, otherwise the HTML is shown as text. The texts are trusted HTML from the eRecht24 API; never route user-supplied content through these views.
:::
