<?php

namespace Cognesy\Polyglot\Embeddings\Traits;

use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Data\Vector;

trait HandlesShortcuts
{
    /**
     * Returns all embeddings for the provided input data.
     */
    public function get() : EmbeddingsResponse {
        return $this->create()->get();
    }

    /**
     * Returns all embeddings for the provided input data.
     *
     * @return Vector[] Array of embedding vectors
     */
    public function vectors() : array {
        return $this->create()->get()->vectors();
    }

    /**
     * Returns the first embedding for the provided input data.
     *
     * @return Vector The first embedding vector
     */
    public function first() : Vector {
        return $this->create()->get()->first();
    }
}