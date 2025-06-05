<?php

namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Polyglot\Embeddings\EmbeddingsRequest;

interface CanMapRequestBody
{
    public function toRequestBody(EmbeddingsRequest $request) : array;
}