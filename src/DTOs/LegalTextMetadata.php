<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\DTOs;

use Carbon\CarbonImmutable;

final readonly class LegalTextMetadata
{
    public function __construct(
        public CarbonImmutable $fetchedAt,
        public ?CarbonImmutable $sourceModifiedAt = null,
    ) {}

    public function toJson(): string
    {
        $payload = [
            'fetched_at' => $this->fetchedAt->toIso8601String(),
            'source_modified_at' => $this->sourceModifiedAt?->toIso8601String(),
        ];

        return (string) json_encode($payload, JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): self
    {
        /** @var array{fetched_at?: string, source_modified_at?: ?string} $data */
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (! isset($data['fetched_at']) || ! is_string($data['fetched_at'])) {
            throw new \InvalidArgumentException('Legal text metadata is missing a valid "fetched_at" value.');
        }

        return new self(
            fetchedAt: CarbonImmutable::parse($data['fetched_at']),
            sourceModifiedAt: isset($data['source_modified_at']) ? CarbonImmutable::parse($data['source_modified_at']) : null,
        );
    }
}
