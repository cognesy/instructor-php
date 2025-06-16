<?php

namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;

interface EmbedRequestAdapter
{
    public function toHttpClientRequest(EmbeddingsRequest $request) : HttpRequest;
}