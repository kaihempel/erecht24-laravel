<?php

declare(strict_types=1);

/**
 * Guards the `.env` reference tables on the EN and DE configuration pages:
 * every environment variable read by config/erecht24.php must be documented
 * on each of them.
 *
 * @return array<int, string>
 */
function erecht24ConfigEnvNames(): array
{
    $config = (string) file_get_contents(dirname(__DIR__, 2).'/config/erecht24.php');

    preg_match_all("/env\(\s*'(ERECHT24_[A-Z0-9_]+)'/", $config, $matches);

    return array_values(array_unique($matches[1]));
}

it('finds the env variables read by the config file', function () {
    expect(erecht24ConfigEnvNames())
        ->not->toBeEmpty()
        ->toContain('ERECHT24_API_KEY', 'ERECHT24_AUTHOR_MAIL', 'ERECHT24_SYNC_TRIES');
});

it('documents every config env variable on the configuration page', function (string $page) {
    $path = dirname(__DIR__, 2).'/'.$page;

    expect($path)->toBeFile();

    $contents = (string) file_get_contents($path);

    foreach (erecht24ConfigEnvNames() as $name) {
        expect(str_contains($contents, '`'.$name.'`'))
            ->toBeTrue(sprintf('%s does not document %s.', $page, $name));
    }
})->with([
    'docs/src/content/docs/en/reference/configuration.md',
    'docs/src/content/docs/de/reference/configuration.md',
]);
