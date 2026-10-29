<?php

declare(strict_types=1);

use KaiHempel\ERecht24\Enums\SealVariant;

it('reports orientation, sizes and suffix', function (SealVariant $variant, bool $vertical, int $w, int $h, int $display, string $suffix) {
    expect($variant->isVertical())->toBe($vertical)
        ->and($variant->width())->toBe($w)
        ->and($variant->height())->toBe($h)
        ->and($variant->displayWidth())->toBe($display)
        ->and($variant->fileSuffix())->toBe($suffix);
})->with([
    [SealVariant::LightHorizontal, false, 1425, 600, 300, 'light-horizontal-large'],
    [SealVariant::DarkHorizontal, false, 1425, 600, 300, 'dark-horizontal-large'],
    [SealVariant::LightVertical, true, 900, 1400, 180, 'light-vertical-large'],
    [SealVariant::DarkVertical, true, 900, 1400, 180, 'dark-vertical-large'],
]);
