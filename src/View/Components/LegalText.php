<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\View\Components;

use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\View\LegalTextResolver;

/**
 * Generic `<x-erecht24::legal-text type="..." />` component.
 */
final class LegalText extends AbstractLegalTextComponent
{
    private readonly LegalTextType $legalTextType;

    /**
     * @throws \InvalidArgumentException when the value is neither a type value nor a file slug
     */
    public function __construct(
        LegalTextResolver $resolver,
        LegalTextType|string $type,
        ?string $lang = null,
    ) {
        parent::__construct($resolver, $lang);

        $this->legalTextType = $type instanceof LegalTextType ? $type : self::parseType($type);
    }

    protected function legalTextType(): LegalTextType
    {
        return $this->legalTextType;
    }

    protected function contentView(array $data): ViewContract
    {
        return View::first(['erecht24::components.legal-text'], $data);
    }

    private static function parseType(string $type): LegalTextType
    {
        $allowed = [];

        foreach (LegalTextType::cases() as $case) {
            if ($type === $case->value || $type === $case->fileSlug()) {
                return $case;
            }

            $allowed[] = $case->value;
            $allowed[] = $case->fileSlug();
        }

        throw new \InvalidArgumentException(sprintf(
            'Invalid legal text type [%s]. Allowed values: %s.',
            $type,
            implode(', ', array_unique($allowed)),
        ));
    }
}
