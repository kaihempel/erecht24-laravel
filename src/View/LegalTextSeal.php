<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\View;

/**
 * Immutable description of the trust seal shown below a legal text.
 */
final readonly class LegalTextSeal
{
    public function __construct(
        public string $url,
        public string $alt,
        public int $width,
        public int $height,
        public int $displayWidth,
    ) {}

    /**
     * Display height in CSS pixels that keeps the intrinsic aspect ratio at `displayWidth`.
     */
    public function displayHeight(): int
    {
        return (int) round($this->displayWidth * $this->height / $this->width);
    }
}
