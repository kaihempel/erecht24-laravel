<?php

declare(strict_types=1);

use KaiHempel\ERecht24\ERecht24ServiceProvider;

it('registers the service provider', function () {
    expect(app()->getProviders(ERecht24ServiceProvider::class))->not->toBeEmpty();
});
