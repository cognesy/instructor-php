<?php

namespace Cognesy\Polyglot\Embeddings\Drivers;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedRequestAdapter;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedResponseAdapter;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\EmbeddingsResponse;
use Psr\EventDispatcher\EventDispatcherInterface;

class BaseEmbedDriver implements CanHandleVectorization
{
    protected EmbeddingsConfig $config;
    protected HttpClient $httpClient;
    protected EventDispatcherInterface $events;

    protected EmbedRequestAdapter $requestAdapter;
    protected EmbedResponseAdapter $responseAdapter;

    public function handle(EmbeddingsRequest $request): HttpClientResponse {
        $clientRequest = $this->requestAdapter->toHttpClientRequest($request);
        return $this->httpClient->handle($clientRequest);
    }

    public function fromData(array $data): ?EmbeddingsResponse {
        return $this->responseAdapter->fromResponse($data);
    }
}
