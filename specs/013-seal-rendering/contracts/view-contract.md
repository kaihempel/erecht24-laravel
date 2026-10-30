# View contract

`erecht24::components.legal-text` variables: `$content` (string), `$type` (LegalTextType), `$lang` (string), `$seal` (?LegalTextSeal, optional; may be undefined in user overrides).

Markup when `$seal` is non-empty, directly after `{!! $content !!}` inside the wrapper:

```html
<div class="erecht24-seal" style="text-align: center;">
    <img src="{url}" alt="{alt}" width="{width}" height="{height}" loading="lazy"
         style="width: {displayWidth}px; max-width: 100%; height: auto;">
</div>
```

`imprint`, `privacy-policy`, `privacy-policy-social-media` forward `'seal' => $seal` to the include. `erecht24::missing` never receives or shows a seal. Component constructors are unchanged.
