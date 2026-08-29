<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Polyglot\Inference\Reasoning\ReasoningCapabilities;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningSelection;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningTranslation;

interface CanTranslateReasoning
{
    public function capabilities(string $model): ReasoningCapabilities;

    public function translate(string $model, ReasoningSelection $selection): ReasoningTranslation;
}
