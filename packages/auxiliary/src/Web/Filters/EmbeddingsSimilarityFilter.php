<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Web\Filters;

use Cognesy\Auxiliary\Web\Contracts\CanFilterContent;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\Vector;

class EmbeddingsSimilarityFilter implements CanFilterContent
{
    private CanCreateEmbeddings $embeddings;
    private Vector $compareTo;
    private float $threshold;

    public function __construct(
        string|Vector $compareTo,
        CanCreateEmbeddings $embeddings,
        float $threshold = 0.7,
    ) {
        $this->threshold = $threshold;
        $this->embeddings = $embeddings;
        if (is_string($compareTo)) {
            $this->compareTo = $this->embed($compareTo);
        } else {
            $this->compareTo = $compareTo;
        }
    }

    #[\Override]
    public function filter(string $content): bool {
        $vector = $this->embed($content);
        return Vector::cosineSimilarity($vector->values(), $this->compareTo->values()) >= $this->threshold;
    }

    private function embed(string $content): Vector {
        $vector = $this->embeddings->create(new EmbeddingsRequest(input: $content))->get()->first();
        if ($vector === null) {
            throw new \RuntimeException('Could not create embedding vector for content.');
        }
        return $vector;
    }
}
