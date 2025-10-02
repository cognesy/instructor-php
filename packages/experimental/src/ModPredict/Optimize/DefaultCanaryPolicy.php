<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Optimize;

use Cognesy\Experimental\ModPredict\Optimize\Contracts\CanaryPolicy;
use Cognesy\Experimental\ModPredict\Optimize\Data\PromptPreset;
use Cognesy\Experimental\ModPredict\Optimize\Data\PromptPresetSelection;
use Cognesy\Experimental\ModPredict\Optimize\Enums\PromptSelectionStrategy;

final class DefaultCanaryPolicy implements CanaryPolicy
{
    public function __construct(private float $percentage = 0.0) {}

    #[\Override]
    public function pick(?PromptPreset $active, array $canaries, string $signatureId, string $modelId): PromptPresetSelection {
        if ($this->percentage <= 0.0 || empty($canaries)) {
            return $active
                ? new PromptPresetSelection($active, PromptSelectionStrategy::Active)
                : new PromptPresetSelection(null, PromptSelectionStrategy::None);
        }

        $r = mt_rand() / mt_getrandmax();

        if ($r <= $this->percentage) {
            $i = mt_rand(0, count($canaries) - 1);
            return new PromptPresetSelection($canaries[$i], PromptSelectionStrategy::Canary);
        }

        return $active
            ? new PromptPresetSelection($active, PromptSelectionStrategy::Active)
            : new PromptPresetSelection(null, PromptSelectionStrategy::None);
    }
}