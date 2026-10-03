<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\View\Components;

use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;
use KaiHempel\ERecht24\Enums\LegalTextType;

final class Imprint extends AbstractLegalTextComponent
{
    protected function legalTextType(): LegalTextType
    {
        return LegalTextType::Imprint;
    }

    protected function contentView(array $data): ViewContract
    {
        return View::first(['erecht24::imprint'], $data);
    }
}
