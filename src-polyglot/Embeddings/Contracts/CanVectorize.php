<?php
namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Polyglot\Embeddings\EmbeddingsResponse;

interface CanVectorize
{
    public function vectorize(array $input, array $options = []) : EmbeddingsResponse;
}
