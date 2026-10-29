<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\View;

use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Exceptions\InvalidConfigurationException;

/**
 * Decides which trust seal (if any) belongs to a legal text type and language.
 * Reads configuration only; performs no I/O.
 */
final class SealResolver
{
    private const ASSET_DIRECTORY = 'vendor/erecht24/';

    public function __construct(private readonly Erecht24Settings $settings) {}

    /**
     * @throws InvalidConfigurationException when the seal setting is invalid
     */
    public function for(LegalTextType $type, string $lang): ?LegalTextSeal
    {
        [$variant, $prefix] = match ($type) {
            LegalTextType::Imprint => [$this->settings->imprintSeal(), 'impressum-siegel-'],
            LegalTextType::PrivacyPolicy => [$this->settings->privacyPolicySeal(), 'datenschutz-siegel-'],
            LegalTextType::PrivacyPolicySocialMedia => [null, ''],
        };

        if ($variant === null) {
            return null;
        }

        return new LegalTextSeal(
            url: asset(self::ASSET_DIRECTORY.$prefix.$variant->fileSuffix().'.webp'),
            alt: $this->alt($type, $lang),
            width: $variant->width(),
            height: $variant->height(),
            displayWidth: $variant->displayWidth(),
        );
    }

    private function alt(LegalTextType $type, string $lang): string
    {
        $german = $lang === 'de';

        return match ($type) {
            LegalTextType::Imprint => $german
                ? 'Impressum – rechtssicher mit eRecht24'
                : 'Legal notice – legally compliant with eRecht24',
            default => $german
                ? 'Datenschutzerklärung – rechtssicher mit eRecht24'
                : 'Privacy policy – legally compliant with eRecht24',
        };
    }
}
