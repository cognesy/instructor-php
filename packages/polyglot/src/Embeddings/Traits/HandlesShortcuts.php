<?php

namespace Cognesy\Polyglot\Embeddings\Traits;

use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Embeddings\EmbeddingsResponse;

trait HandlesShortcuts
{
    /**
     * Returns all embeddings for the provided input data.
     *
     * @return Vector[] Array of embedding vectors
     */
    public function get() : array {
        return $this->create()->all();
    }

    /**
     * Returns the first embedding for the provided input data.
     *
     * @return Vector The first embedding vector
     */
    public function first() : EmbeddingsResponse {
        return $this->create()->first();
    }
}