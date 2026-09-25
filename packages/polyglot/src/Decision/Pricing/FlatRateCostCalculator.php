<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Pricing;

use Cognesy\Polyglot\Decision\Data\DecisionPricing;
use Cognesy\Polyglot\Decision\Data\DecisionUsage;
use Cognesy\Polyglot\Support\Pricing\Cost;

final readonly class FlatRateCostCalculator
{
    public function calculate(DecisionUsage $usage, DecisionPricing $pricing): ?Cost
    {
        $inputTokens = $usage->inputTokens();
        $outputTokens = $usage->outputTokens();
        if (($pricing->inputPerMToken > 0 && $inputTokens === null)
            || ($pricing->outputPerMToken > 0 && $outputTokens === null)
        ) {
            return null;
        }

        $input = (($inputTokens ?? 0) / 1_000_000) * $pricing->inputPerMToken;
        $output = (($outputTokens ?? 0) / 1_000_000) * $pricing->outputPerMToken;

        return new Cost(
            total: round($input + $output, 6),
            breakdown: ['input' => $input, 'output' => $output],
        );
    }
}
