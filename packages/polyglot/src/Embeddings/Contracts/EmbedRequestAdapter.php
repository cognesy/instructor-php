<?php

namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\Embeddings\EmbeddingsRequest;

interface EmbedRequestAdapter
{
    public function toHttpClientRequest(EmbeddingsRequest $request) : HttpClientRequest;
}