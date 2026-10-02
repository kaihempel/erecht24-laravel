<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\DTOs;

use KaiHempel\ERecht24\Enums\LegalTextType;

final readonly class LegalText
{
    public function __construct(
        public LegalTextType $type,
        public ?string $htmlDe = null,
        public ?string $htmlEn = null,
        public ?string $created = null,
        public ?string $modified = null,
        public ?string $pushed = null,
        public ?string $warnings = null,
    ) {}

    public function html(string $language): ?string
    {
        return match (strtolower($language)) {
            'de' => $this->htmlDe,
            'en' => $this->htmlEn,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApiResponse(LegalTextType $type, array $data): self
    {
        return new self(
            type: $type,
            htmlDe: isset($data['html_de']) ? (string) $data['html_de'] : null,
            htmlEn: isset($data['html_en']) ? (string) $data['html_en'] : null,
            created: isset($data['created']) ? (string) $data['created'] : null,
            modified: isset($data['modified']) ? (string) $data['modified'] : null,
            pushed: isset($data['pushed']) ? (string) $data['pushed'] : null,
            warnings: isset($data['warnings']) ? (string) $data['warnings'] : null,
        );
    }
}
