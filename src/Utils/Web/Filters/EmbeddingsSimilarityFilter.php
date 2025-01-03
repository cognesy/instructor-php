<?php

namespace Cognesy\Instructor\Utils\Web\Filters;

use Cognesy\Instructor\Extras\Embeddings\Data\Vector;
use Cognesy\Instructor\Extras\Embeddings\Embeddings;
use Cognesy\Instructor\Utils\Web\Contracts\CanFilterContent;

class EmbeddingsSimilarityFilter implements CanFilterContent
{
    private Embeddings $embeddings;

    private Vector $compareTo;
    private float $threshold;
    private string $connection;

    public function __construct(
        string|Vector $compareTo,
        float $threshold = 0.7,
        string $connection = 'openai',
    ) {
        $this->connection = $connection;
        $this->threshold = $threshold;
        $this->embeddings = (new Embeddings)->withConnection($this->connection);
        if (is_string($compareTo)) {
            $this->compareTo = $this->embeddings->create($compareTo)->first();
        }
    }

    public function filter(string $content): bool {
        $vector = $this->embeddings->create($content)->first();
        return Vector::cosineSimilarity($vector, $this->compareTo) >= $this->threshold;
    }
}
