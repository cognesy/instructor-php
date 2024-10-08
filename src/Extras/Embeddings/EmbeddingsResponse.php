<?php

namespace Cognesy\Instructor\Extras\Embeddings;

use Cognesy\Instructor\Extras\Embeddings\Data\Vector;

class EmbeddingsResponse
{
    public function __construct(
        /** @var Vector[] */
        public array $vectors,
        public int $inputTokens,
        public int $outputTokens,
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

    /**
     * @param int $index
     * @return EmbeddingsResponse[]
     */
    public function split(int $index) : array {
        return [
            new EmbeddingsResponse(
                array_slice($this->vectors, 0, $index),
                $this->inputTokens,
                $this->outputTokens,
            ),
            new EmbeddingsResponse(
                array_slice($this->vectors, $index),
                0,
                0,
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
        return $this->inputTokens + $this->outputTokens;
    }
}
