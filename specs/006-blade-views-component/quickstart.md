# Quickstart: Blade Views and Component

1. Sync texts: `php artisan erecht24:sync`
2. Use in a Blade view: `<x-erecht24::imprint />`
3. Force language: `<x-erecht24::privacy-policy lang="en" />`
4. Customize: `php artisan vendor:publish --tag=erecht24-views`, then edit `resources/views/vendor/erecht24/components/legal-text.blade.php`.

## Verify
- Empty store + `APP_DEBUG=true` → neutral message with `php artisan erecht24:sync` hint; with `APP_DEBUG=false` → no hint.
- `composer test && composer lint && composer analyse`.
