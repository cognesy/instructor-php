<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Polyglot\Embeddings\Data\EmbeddingsPricing;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsUsage;
use Cognesy\Polyglot\Pricing\Cost;

/**
 * Strategy for calculating cost from embeddings usage and pricing rates.
 */
interface CanCalculateEmbeddingsCost
{
    public function calculate(EmbeddingsUsage $usage, EmbeddingsPricing $pricing): Cost;
}
