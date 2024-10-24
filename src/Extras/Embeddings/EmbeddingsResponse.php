<?php

namespace Cognesy\Instructor\Extras\Embeddings;

use Cognesy\Instructor\Extras\Embeddings\Data\Vector;
use Cognesy\Instructor\Features\LLM\Data\Usage;

class EmbeddingsResponse
{
    public function __construct(
        /** @var Vector[] */
        public array $vectors,
        public ?Usage $usage,
    ) {}

    public function first() : Vector {
        return $this->vectors[0];
    }

    public function last() : Vector {
        return $this->vectors[count($this->vectors) - 1];
    }

    public function all() : array {
        return $this->vectors;
    }

    public function usage() : Usage {
        return $this->usage;
    }

    /**
     * @param int $index
     * @return EmbeddingsResponse[]
     */
    public function split(int $index) : array {
        return [
            new EmbeddingsResponse(
                vectors: array_slice($this->vectors, 0, $index),
                usage: Usage::copy($this->usage()), // TODO: token split is arbitrary
            ),
            new EmbeddingsResponse(
                vectors: array_slice($this->vectors, $index),
                usage: new Usage(), // TODO: token split is arbitrary
            ),
        ];
    }

    public function toValuesArray() : array {
        return array_map(
            fn(Vector $vector) => $vector->values(),
            $this->vectors
        );
    }

    public function totalTokens() : int {
        return $this->usage()->total();
    }
}
