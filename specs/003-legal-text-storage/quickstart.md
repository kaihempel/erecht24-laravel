# Quickstart: Legal Text Storage Layer

## Resolving the store

```php
use KaiHempel\ERecht24\Storage\LegalTextStore;

$store = app(LegalTextStore::class); // singleton
```

## Saving a legal text

```php
use KaiHempel\ERecht24\Enums\LegalTextType;

$store->put(LegalTextType::Imprint, 'de', '<p>Impressum …</p>');
```

## Reading it back

```php
$html = $store->get(LegalTextType::Imprint, 'de'); // '<p>Impressum …</p>'
$store->get(LegalTextType::Imprint, 'en'); // null — nothing saved for 'en' yet
```

## Checking freshness

```php
if ($store->has(LegalTextType::Imprint, 'de')) {
    $savedAt = $store->lastModified(LegalTextType::Imprint, 'de'); // CarbonImmutable
}
```

## Removing stored content

```php
$store->forget(LegalTextType::Imprint, 'de');
$store->get(LegalTextType::Imprint, 'de'); // null again
```

## Invalid language

```php
$store->get(LegalTextType::Imprint, 'fr'); // throws \InvalidArgumentException
```

## Testing with a faked disk

```php
use Illuminate\Support\Facades\Storage;

Storage::fake(config('erecht24.disk'));

$store->put(LegalTextType::Imprint, 'de', '<p>Impressum</p>');

Storage::disk(config('erecht24.disk'))->assertExists('erecht24/imprint.de.html');
expect(Storage::disk(config('erecht24.disk'))->exists('erecht24/imprint.de.html.tmp'))->toBeFalse();
```

## On-disk layout produced

```
storage/app/erecht24/
├── imprint.de.html
├── imprint.de.meta.json
├── imprint.en.html
├── imprint.en.meta.json
├── privacy-policy.de.html
├── privacy-policy.de.meta.json
├── privacy-policy-social-media.de.html
└── privacy-policy-social-media.de.meta.json
```
