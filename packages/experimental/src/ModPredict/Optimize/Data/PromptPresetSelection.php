<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Optimize\Data;

use Cognesy\Experimental\ModPredict\Optimize\Enums\PromptSelectionStrategy;

final class PromptPresetSelection
{
    public function __construct(
        public readonly ?PromptPreset $preset,
        public readonly PromptSelectionStrategy $strategy,
    ) {}
}