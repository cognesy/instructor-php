<?php

namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Polyglot\Embeddings\EmbeddingsResponse;

interface EmbedResponseAdapter
{
    public function fromResponse(array $data): EmbeddingsResponse;
}