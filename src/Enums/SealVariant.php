<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Enums;

enum SealVariant: string
{
    case LightHorizontal = 'light-horizontal';
    case LightVertical = 'light-vertical';
    case DarkHorizontal = 'dark-horizontal';
    case DarkVertical = 'dark-vertical';

    public function isVertical(): bool
    {
        return match ($this) {
            self::LightVertical, self::DarkVertical => true,
            self::LightHorizontal, self::DarkHorizontal => false,
        };
    }

    /**
     * Intrinsic image width in pixels.
     */
    public function width(): int
    {
        return $this->isVertical() ? 900 : 1425;
    }

    /**
     * Intrinsic image height in pixels.
     */
    public function height(): int
    {
        return $this->isVertical() ? 1400 : 600;
    }

    /**
     * Recommended display width in CSS pixels.
     */
    public function displayWidth(): int
    {
        return $this->isVertical() ? 180 : 300;
    }

    /**
     * File name suffix of the shipped WebP, e.g. `light-horizontal-large`.
     */
    public function fileSuffix(): string
    {
        return $this->value.'-large';
    }
}
