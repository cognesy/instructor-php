<?php

namespace Cognesy\Auxiliary\Web\Filters;

use Cognesy\Auxiliary\Web\Contracts\CanFilterContent;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Embeddings\Embeddings;

class EmbeddingsSimilarityFilter implements CanFilterContent
{
    private Embeddings $embeddings;

    private Vector $compareTo;
    private float $threshold;
    private string $preset;

    public function __construct(
        string|Vector $compareTo,
        float $threshold = 0.7,
        string $preset = 'openai',
    ) {
        $this->preset = $preset;
        $this->threshold = $threshold;
        $this->embeddings = (new Embeddings)->using($this->preset);
        if (is_string($compareTo)) {
            $this->compareTo = $this->embeddings->with($compareTo)->first();
        }
    }

    public function filter(string $content): bool {
        $vector = $this->embeddings->with($content)->first();
        return Vector::cosineSimilarity($vector->values(), $this->compareTo->values()) >= $this->threshold;
    }
}
