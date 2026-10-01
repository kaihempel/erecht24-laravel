<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use KaiHempel\ERecht24\Config\Erecht24Settings;

it('returns typed values for base url, disk, directory, and queue settings', function () {
    $config = new Repository(['erecht24' => [
        'base_url' => 'https://api.e-recht24.de/v2',
        'disk' => 'local',
        'directory' => 'erecht24',
        'queue' => [
            'connection' => null,
            'name' => null,
        ],
    ]]);

    $settings = new Erecht24Settings($config);

    expect($settings->baseUrl())->toBe('https://api.e-recht24.de/v2')
        ->and($settings->disk())->toBe('local')
        ->and($settings->directory())->toBe('erecht24')
        ->and($settings->queueConnection())->toBeNull()
        ->and($settings->queueName())->toBeNull();
});

it('returns configured queue connection and name', function () {
    $config = new Repository(['erecht24' => [
        'queue' => [
            'connection' => 'redis',
            'name' => 'erecht24-jobs',
        ],
    ]]);

    $settings = new Erecht24Settings($config);

    expect($settings->queueConnection())->toBe('redis')
        ->and($settings->queueName())->toBe('erecht24-jobs');
});
