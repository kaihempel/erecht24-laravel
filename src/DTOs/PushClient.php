<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\DTOs;

final readonly class PushClient
{
    public function __construct(
        public ?int $id = null,
        public string $pushMethod = '',
        public string $pushUri = '',
        public string $cms = '',
        public ?string $cmsVersion = null,
        public string $pluginName = '',
        public ?string $authorMail = null,
        public ?string $secret = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: isset($data['client_id']) ? (int) $data['client_id'] : null,
            pushMethod: (string) ($data['push_method'] ?? ''),
            pushUri: (string) ($data['push_uri'] ?? ''),
            cms: (string) ($data['cms'] ?? ''),
            cmsVersion: isset($data['cms_version']) ? (string) $data['cms_version'] : null,
            pluginName: (string) ($data['plugin_name'] ?? ''),
            authorMail: isset($data['author_mail']) ? (string) $data['author_mail'] : null,
            secret: isset($data['secret']) ? (string) $data['secret'] : null,
        );
    }
}
