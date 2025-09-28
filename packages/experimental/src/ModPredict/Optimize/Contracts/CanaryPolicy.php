<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Optimize\Contracts;

use Cognesy\Experimental\ModPredict\Optimize\Data\PromptPreset;
use Cognesy\Experimental\ModPredict\Optimize\Data\PromptPresetSelection;

interface CanaryPolicy
{
    /** @param PromptPreset[] $canaries */
    public function pick(?PromptPreset $active, array $canaries, string $signatureId, string $modelId): PromptPresetSelection;
}