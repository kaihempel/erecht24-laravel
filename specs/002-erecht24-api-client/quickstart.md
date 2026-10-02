# Quickstart: eRecht24 API Client

**Contract**: [contracts/erecht24-client.md](./contracts/erecht24-client.md)

## Prerequisites

- Package config published/overridden with real credentials (from issue #3):
  `ERECHT24_API_KEY`, `ERECHT24_PLUGIN_KEY` set in `.env`.

## Fetch a legal text

```php
use KaiHempel\ERecht24\Erecht24Client;
use KaiHempel\ERecht24\Enums\LegalTextType;

$client = app(Erecht24Client::class);

$imprint = $client->legalText(LegalTextType::Imprint);
echo $imprint->html('de');
```

## Register, update, and remove a push client

```php
use KaiHempel\ERecht24\DTOs\PushClient;

$registered = $client->createClient(new PushClient(
    pushMethod: 'POST',
    pushUri: 'https://example.com/api/erecht24/push',
    cms: 'Laravel',
    cmsVersion: app()->version(),
    pluginName: 'erecht24-laravel',
    authorMail: 'dev@example.com',
));

// Persist $registered->id and $registered->secret — the secret authenticates incoming pushes.

$updated = $client->updateClient(new PushClient(
    id: $registered->id,
    pushMethod: 'POST',
    pushUri: 'https://example.com/api/erecht24/push-v2',
    cms: 'Laravel',
    pluginName: 'erecht24-laravel',
));
// $updated->secret is a NEW secret — re-persist it, the old one is now invalid.

$client->fireTestPush($registered->id); // defaults to type: 'ping'

$client->deleteClient($registered->id);
```

## Handling errors

```php
use KaiHempel\ERecht24\Exceptions\Erecht24ApiException;
use KaiHempel\ERecht24\Exceptions\Erecht24AuthenticationException;

try {
    $client->legalText(LegalTextType::PrivacyPolicy);
} catch (Erecht24AuthenticationException $e) {
    // Invalid/expired API key or plugin key. $e->getMessage() never contains the key itself.
} catch (Erecht24ApiException $e) {
    // Any other failure: not-found, quota exceeded, validation error, or service unavailable.
    // $e->status and $e->apiMessage carry the detail.
}
```

## Testing in a consuming app or this package's own test suite

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'api.e-recht24.de/v2/imprint' => Http::response([
        'html_de' => '<h1>Impressum</h1>',
        'html_en' => '<h1>Imprint</h1>',
    ], 200),
]);

$text = app(Erecht24Client::class)->legalText(LegalTextType::Imprint);
expect($text->html('de'))->toBe('<h1>Impressum</h1>');
```
