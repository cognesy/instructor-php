<?php

namespace Cognesy\Polyglot\Embeddings\Drivers\Jina;

use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedRequestAdapter;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;

class JinaRequestAdapter implements EmbedRequestAdapter
{
    public function __construct(
        protected EmbeddingsConfig $config,
        protected CanMapRequestBody $bodyFormat,
    ) {}

    public function toHttpClientRequest(EmbeddingsRequest $request): HttpClientRequest {
         return new HttpClientRequest(
            url: $this->getEndpointUrl(),
            method: 'POST',
            headers: $this->getRequestHeaders(),
            body: $this->bodyFormat->toRequestBody($request),
            options: [],
        );
    }

    // INTERNAL /////////////////////////////////////////////

    protected function getEndpointUrl(): string {
        return "{$this->config->apiUrl}{$this->config->endpoint}";
    }

    protected function getRequestHeaders(): array {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->config->apiKey}",
        ];
    }
}