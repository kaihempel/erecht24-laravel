<?php

declare(strict_types=1);

dataset('env_overrides', [
    ['ERECHT24_API_KEY', 'api_key', 'env-api-key'],
    ['ERECHT24_PLUGIN_KEY', 'plugin_key', 'env-plugin-key'],
    ['ERECHT24_PUSH_SECRET', 'push_secret', 'env-push-secret'],
    ['ERECHT24_PUSH_PATH', 'push_path', '/custom/push'],
    ['ERECHT24_BASE_URL', 'base_url', 'https://example.test/api'],
    ['ERECHT24_TEXT_LANGUAGES', 'text_languages', 'de'],
    ['ERECHT24_DISK', 'disk', 's3'],
    ['ERECHT24_DIRECTORY', 'directory', 'custom-dir'],
    ['ERECHT24_TIMEOUT', 'timeout', '30'],
    ['ERECHT24_PUSH_RATE_LIMIT', 'push_rate_limit', '60'],
]);

it('overrides erecht24 config via env vars', function (string $envKey, string $configKey, string $value) {
    putenv("{$envKey}={$value}");
    $_ENV[$envKey] = $value;
    $_SERVER[$envKey] = $value;

    $config = require __DIR__.'/../../config/erecht24.php';

    expect($config[$configKey])->toBe($value);

    putenv($envKey);
    unset($_ENV[$envKey], $_SERVER[$envKey]);
})->with('env_overrides');

it('maps ERECHT24_PUSH_ENABLED=false to a disabled push endpoint', function () {
    putenv('ERECHT24_PUSH_ENABLED=false');
    $_ENV['ERECHT24_PUSH_ENABLED'] = 'false';
    $_SERVER['ERECHT24_PUSH_ENABLED'] = 'false';

    $config = require __DIR__.'/../../config/erecht24.php';

    expect($config['push_enabled'])->toBeFalse();

    putenv('ERECHT24_PUSH_ENABLED');
    unset($_ENV['ERECHT24_PUSH_ENABLED'], $_SERVER['ERECHT24_PUSH_ENABLED']);
});
