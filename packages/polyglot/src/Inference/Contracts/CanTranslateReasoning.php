<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Polyglot\Inference\Reasoning\ReasoningCapabilities;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelection;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningTranslation;

interface CanTranslateReasoning
{
    public function translate(
        ReasoningCapabilities $capabilities,
        ReasoningSelection $selection,
    ): ReasoningTranslation;
}
