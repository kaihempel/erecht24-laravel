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

## Customizing the views

```bash
php artisan vendor:publish --tag=erecht24-views
```

The views are copied to `resources/views/vendor/erecht24/` and take precedence over the package views: `components/legal-text`, `imprint`, `privacy-policy`, `privacy-policy-social-media` and `missing`. The content views receive `$content`, `$type` (a `LegalTextType`) and `$lang`. The English message in `missing` can be translated there.

:::danger
Keep `{!! $content !!}` unescaped, otherwise the HTML is shown as text. The texts are trusted HTML from the eRecht24 API; never route user-supplied content through these views.
:::

## Trust seals

```bash
php artisan vendor:publish --tag=erecht24-assets
```

The seal images (WebP) are copied to `public/vendor/erecht24/`, for example `impressum-siegel-light-horizontal-large.webp` and `datenschutz-siegel-dark-vertical-large.webp`. They are only needed if you configure `ERECHT24_IMPRINT_SEAL` or `ERECHT24_PRIVACY_POLICY_SEAL`.
