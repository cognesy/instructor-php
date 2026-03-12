<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Pricing;

use Cognesy\Polyglot\Inference\Contracts\CanCalculateInferenceCost;
use Cognesy\Polyglot\Inference\Data\InferencePricing;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Pricing\Cost;

/**
 * Flat-rate cost calculator: applies per-million-token rates to each usage category.
 * Stateless — same instance can be reused across calls with different usage/pricing.
 */
final class FlatRateCostCalculator implements CanCalculateInferenceCost
{
    public function calculate(InferenceUsage $usage, InferencePricing $pricing): Cost
    {
        $input = ($usage->inputTokens / 1_000_000) * $pricing->inputPerMToken;
        $output = ($usage->outputTokens / 1_000_000) * $pricing->outputPerMToken;
        $cacheRead = ($usage->cacheReadTokens / 1_000_000) * $pricing->cacheReadPerMToken;
        $cacheWrite = ($usage->cacheWriteTokens / 1_000_000) * $pricing->cacheWritePerMToken;
        $reasoning = ($usage->reasoningTokens / 1_000_000) * $pricing->reasoningPerMToken;

        return new Cost(
            total: round($input + $output + $cacheRead + $cacheWrite + $reasoning, 6),
            breakdown: [
                'input' => $input,
                'output' => $output,
                'cacheRead' => $cacheRead,
                'cacheWrite' => $cacheWrite,
                'reasoning' => $reasoning,
            ],
        );
    }
}
