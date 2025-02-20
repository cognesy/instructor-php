<?php

namespace Cognesy\LLM\Embeddings\Drivers;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\LLM\Embeddings\Contracts\CanVectorize;
use Cognesy\LLM\Embeddings\Data\EmbeddingsConfig;
use Cognesy\LLM\Embeddings\Data\Vector;
use Cognesy\LLM\Embeddings\EmbeddingsResponse;
use Cognesy\LLM\Http\Contracts\CanHandleHttp;
use Cognesy\LLM\Http\Data\HttpClientRequest;
use Cognesy\LLM\Http\HttpClient;
use Cognesy\LLM\LLM\Data\Usage;

class JinaDriver implements CanVectorize
{
    public function __construct(
        protected EmbeddingsConfig $config,
        protected ?CanHandleHttp $httpClient = null,
        protected ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->httpClient = $httpClient ?? HttpClient::make(events: $this->events);
    }

    public function vectorize(array $input, array $options = []): EmbeddingsResponse {
        $request = new HttpClientRequest(
            url: $this->getEndpointUrl(),
            method: 'POST',
            headers: $this->getRequestHeaders(),
            body: $this->getRequestBody($input, $options),
            options: [],
        );
        $response = $this->httpClient->handle($request);
        return $this->toResponse(json_decode($response->getContents(), true));
    }

    // INTERNAL /////////////////////////////////////////////////

    protected function getEndpointUrl(): string {
        return "{$this->config->apiUrl}{$this->config->endpoint}";
    }

    protected function getRequestHeaders(): array {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->config->apiKey}",
        ];
    }

    protected function getRequestBody(array $input, array $options) : array {
        $body = array_filter(array_merge([
            'model' => $this->config->model,
            'normalized' => true,
            'embedding_type' => 'float',
            'input' => $input,
        ], $options));
        if ($this->config->model === 'jina-colbert-v2') {
            $body['input_type'] = $options['input_type'] ?? 'document';
            $body['dimensions'] = $options['dimensions'] ?? 128;
        }
        return $body;
    }

    protected function toResponse(array $response) : EmbeddingsResponse {
        return new EmbeddingsResponse(
            vectors: array_map(
                fn($item) => new Vector(values: $item['embedding'], id: $item['index']),
                $response['data']
            ),
            usage: $this->makeUsage($response),
        );
    }

    protected function makeUsage(array $response) : Usage {
        return new Usage(
            inputTokens: $response['usage']['prompt_tokens'] ?? 0,
            outputTokens: ($response['usage']['total_tokens'] ?? 0) - ($response['usage']['prompt_tokens'] ?? 0),
        );
    }
}
