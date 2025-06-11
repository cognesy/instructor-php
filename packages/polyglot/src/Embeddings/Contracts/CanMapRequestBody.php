<?php

namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;

interface CanMapRequestBody
{
    public function toRequestBody(EmbeddingsRequest $request) : array;
}