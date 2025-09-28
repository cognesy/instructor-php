<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Optimize\Contracts;

use Cognesy\Experimental\ModPredict\Optimize\Data\PromptPreset;

interface PromptRepository
{
    public function getActive(string $signatureId, string $modelId): ?PromptPreset;

    /** @return PromptPreset[] */
    public function getCanaries(string $signatureId, string $modelId): array;

    public function publish(PromptPreset $preset): void;

    public function activate(string $signatureId, string $modelId, string $version): void;
}

