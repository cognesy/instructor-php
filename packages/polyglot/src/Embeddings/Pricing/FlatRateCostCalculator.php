<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Pricing;

use Cognesy\Polyglot\Embeddings\Contracts\CanCalculateEmbeddingsCost;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsPricing;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsUsage;
use Cognesy\Polyglot\Pricing\Cost;

/**
 * Flat-rate cost calculator: applies per-million-token rate to input usage.
 * Stateless — same instance can be reused across calls with different usage/pricing.
 */
final class FlatRateCostCalculator implements CanCalculateEmbeddingsCost
{
    public function calculate(EmbeddingsUsage $usage, EmbeddingsPricing $pricing): Cost
    {
        $input = ($usage->inputTokens / 1_000_000) * $pricing->inputPerMToken;

        return new Cost(
            total: round($input, 6),
            breakdown: [
                'input' => $input,
            ],
        );
    }
}
