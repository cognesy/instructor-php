<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\LLM\Data\Usage;

/**
 * EmbeddingsResponse represents the response from an embeddings request
 */
class EmbeddingsResponse
{
    public function __construct(
        /** @var Vector[] */
        public array $vectors,
        public ?Usage $usage,
    ) {}

    /**
     * Get the first vector
     * @return Vector
     */
    public function first() : Vector {
        return $this->vectors[0];
    }

    /**
     * Get the last vector
     * @return Vector
     */
    public function last() : Vector {
        return $this->vectors[count($this->vectors) - 1];
    }

    /**
     * Get all vectors
     * @return Vector[]
     */
    public function all() : array {
        return $this->vectors;
    }

    /**
     * Get the number of vectors
     * @return Usage
     */
    public function usage() : Usage {
        return $this->usage;
    }

    /**
     * Split the vectors into two EmbeddingsResponse objects
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

    /**
     * Get the values of all vectors
     * @return array
     */
    public function toValuesArray() : array {
        return array_map(
            fn(Vector $vector) => $vector->values(),
            $this->vectors
        );
    }

    /**
     * Get the total number of tokens
     * @return int
     */
    public function totalTokens() : int {
        return $this->usage()->total();
    }
}
