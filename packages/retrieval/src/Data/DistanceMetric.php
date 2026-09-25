<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Data;

use Cognesy\Polyglot\Embeddings\Data\Vector;

enum DistanceMetric: string
{
    case Cosine = 'cosine';
    case Euclidean = 'euclidean';
    case DotProduct = 'dot_product';

    public function compare(Vector $left, Vector $right): float
    {
        return $left->compareTo($right, $this->value);
    }

    public function higherIsBetter(): bool
    {
        return $this !== self::Euclidean;
    }
}
