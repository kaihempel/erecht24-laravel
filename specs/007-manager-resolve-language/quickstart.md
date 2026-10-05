# Quickstart: `ERecht24::resolve()`

## Use it (consuming app)

```php
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Facades\ERecht24;

$text = ERecht24::resolve(LegalTextType::Imprint); // uses app locale, or pass 'en'

return Inertia::render('Legal/Show', [
    'html' => $text?->content,
    'lang' => $text?->lang,                 // set as lang attribute on the wrapper
    'isFallback' => $text?->isFallback() ?? false, // show "only available in German"
]);
```

`null` means the text has not been synced yet.

## Verify locally (package)

```bash
composer test -- --filter='resolve|fallback|requested'
composer lint
composer analyse
composer test
```

Manual check in a Testbench/tinker session:

```php
config(['erecht24.text_languages' => 'de,en']);
app(\KaiHempel\ERecht24\Storage\LegalTextStore::class)->put(\KaiHempel\ERecht24\Enums\LegalTextType::Imprint, 'de', '<p>DE</p>');
app()->setLocale('en');
$r = ERecht24::resolve('imprint');
// $r->lang === 'de', $r->requestedLang === 'en', $r->isFallback() === true
```
