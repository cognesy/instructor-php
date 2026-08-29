<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Reasoning;

use Cognesy\Polyglot\Inference\Contracts\CanTranslateReasoning;
use InvalidArgumentException;

final readonly class UnsupportedReasoningTranslator implements CanTranslateReasoning
{
    public function capabilities(string $model): ReasoningCapabilities
    {
        return ReasoningCapabilities::unknown();
    }

    public function translate(string $model, ReasoningSelection $selection): ReasoningTranslation
    {
        if ($selection->isDefault()) {
            return ReasoningTranslation::omitted($selection);
        }

        throw new InvalidArgumentException(
            "Reasoning capabilities are unknown for model {$model}; use raw options explicitly.",
        );
    }
}
