<?php
namespace Cognesy\Instructor\Extras\Embeddings\Contracts;

use Cognesy\Instructor\Extras\Embeddings\EmbeddingsResponse;

interface CanVectorize
{
    public function vectorize(array $input, array $options = []) : EmbeddingsResponse;
}
