<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Optimize;

use Cognesy\Experimental\ModPredict\Optimize\Contracts\CanaryPolicy;
use Cognesy\Experimental\ModPredict\Optimize\Contracts\PromptRepository;
use Cognesy\Experimental\ModPredict\Optimize\Data\PromptPresetSelection;
use Cognesy\Experimental\ModPredict\Optimize\Enums\PromptSelectionStrategy;

final class PromptResolver
{
    public function __construct(
        private PromptRepository $repo,
        private ?CanaryPolicy $canary = null,
    ) {}

    public function resolve(string $signatureId, string $modelId): PromptPresetSelection {
        $active = $this->repo->getActive($signatureId, $modelId);
        $canaries = $this->repo->getCanaries($signatureId, $modelId);
        if ($this->canary) {
            return $this->canary->pick($active, $canaries, $signatureId, $modelId);
        }
        if ($active) {
            return new PromptPresetSelection($active, PromptSelectionStrategy::Active);
        }
        return new PromptPresetSelection(null, PromptSelectionStrategy::None);
    }
}

