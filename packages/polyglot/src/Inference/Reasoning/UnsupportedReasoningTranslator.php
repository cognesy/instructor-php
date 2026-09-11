<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Reasoning;

use Cognesy\Polyglot\Inference\Contracts\CanTranslateReasoning;
use InvalidArgumentException;

final readonly class UnsupportedReasoningTranslator implements CanTranslateReasoning
{
    public function translate(
        ReasoningCapabilities $capabilities,
        ReasoningSelection $selection,
    ): ReasoningTranslation {
        if ($selection->isDefault()) {
            return ReasoningTranslation::omitted($selection);
        }

        throw new InvalidArgumentException(
            'The selected inference driver has no typed reasoning wire format; use raw options explicitly.',
        );
    }
}
