<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Data;

/**
 * EmbeddingsResponse represents the response from an embeddings request
 */
class EmbeddingsResponse
{
    /** @var Vector[] */
    private array $vectors;
    private EmbeddingsUsage $usage;

    public function __construct(
        array $vectors = [],
        ?EmbeddingsUsage $usage = null
    ) {
        $this->vectors = $vectors;
        $this->usage = $usage ?? new EmbeddingsUsage();
    }

    /**
     * Get the first vector
     * @return Vector
     */
    public function first() : ?Vector {
        if (count($this->vectors) === 0) {
            return null;
        }
        return $this->vectors[0];
    }

    /**
     * Get the last vector
     * @return Vector
     */
    public function last() : ?Vector {
        if (count($this->vectors) === 0) {
            return null;
        }
        return $this->vectors[count($this->vectors) - 1];
    }

    /**
     * Split the response vectors into two parts at the given index
     *
     * @param int $index
     * @return array{0: Vector[], 1: Vector[]}
     */
    public function split(int $index) : array {
        return [
            array_slice($this->vectors, 0, $index),
            array_slice($this->vectors, $index),
        ];
    }

    /**
     * Get result vectors
     * @return Vector[]
     */
    public function vectors() : array {
        return $this->vectors;
    }

    /**
     * Get result vectors
     * @return Vector[]
     */
    public function all() : array {
        return $this->vectors();
    }

    /**
     * @return EmbeddingsUsage
     */
    public function usage() : EmbeddingsUsage {
        return $this->usage;
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

    public function toArray() : array {
        return [
            'vectors' => array_map(fn(Vector $vector) => $vector->toArray(), $this->vectors),
            'usage' => $this->usage->toArray(),
        ];
    }
}
