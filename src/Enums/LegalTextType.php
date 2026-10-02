<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Enums;

enum LegalTextType: string
{
    case Imprint = 'imprint';
    case PrivacyPolicy = 'privacyPolicy';
    case PrivacyPolicySocialMedia = 'privacyPolicySocialMedia';

    /**
     * A stable, file-name-safe identifier distinct from the wire value, for consumers that
     * cache/store legal text content locally.
     */
    public function fileSlug(): string
    {
        return match ($this) {
            self::Imprint => 'imprint',
            self::PrivacyPolicy => 'privacy-policy',
            self::PrivacyPolicySocialMedia => 'privacy-policy-social-media',
        };
    }
}
