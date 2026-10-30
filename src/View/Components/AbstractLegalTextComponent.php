<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\View\Components;

use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\View\Component;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Exceptions\InvalidConfigurationException;
use KaiHempel\ERecht24\View\LegalTextResolver;
use KaiHempel\ERecht24\View\LegalTextSeal;
use KaiHempel\ERecht24\View\SealResolver;

/**
 * Shared rendering for all legal text components: resolve a stored text (never
 * calls the eRecht24 API) and show it, or show the `erecht24::missing` view.
 *
 * @internal
 */
abstract class AbstractLegalTextComponent extends Component
{
    public function __construct(
        protected readonly LegalTextResolver $resolver,
        protected readonly ?string $lang = null,
    ) {}

    abstract protected function legalTextType(): LegalTextType;

    /**
     * Builds the view that shows the resolved text.
     *
     * @param  array{content: string, type: LegalTextType, lang: string, seal: ?LegalTextSeal}  $data
     */
    abstract protected function contentView(array $data): ViewContract;

    public function render(): ViewContract
    {
        $type = $this->legalTextType();
        $resolved = $this->resolver->resolve($type, $this->lang);

        if ($resolved === null) {
            return View::first(['erecht24::missing'], ['type' => $type]);
        }

        return $this->contentView([
            'content' => $resolved->content,
            'type' => $type,
            'lang' => $resolved->lang,
            'seal' => $this->resolveSeal($type, $resolved->lang),
        ]);
    }

    /**
     * A cosmetic seal setting must never take down a legal page: an invalid
     * value is logged and the text renders without seal.
     */
    private function resolveSeal(LegalTextType $type, string $lang): ?LegalTextSeal
    {
        try {
            return app(SealResolver::class)->for($type, $lang);
        } catch (InvalidConfigurationException $e) {
            Log::warning('eRecht24: invalid seal configuration, rendering without seal.', ['reason' => $e->getMessage()]);

            return null;
        }
    }
}
