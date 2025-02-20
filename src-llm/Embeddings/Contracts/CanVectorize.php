<?php
namespace Cognesy\LLM\Embeddings\Contracts;

use Cognesy\LLM\Embeddings\EmbeddingsResponse;

interface CanVectorize
{
    public function vectorize(array $input, array $options = []) : EmbeddingsResponse;
}
