<?php

namespace Cognesy\Instructor\Extras\Embeddings\Drivers;

use Cognesy\Instructor\Extras\Embeddings\Contracts\CanVectorize;
use Cognesy\Instructor\Extras\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Instructor\Extras\Embeddings\Data\Vector;
use Cognesy\Instructor\Extras\Embeddings\EmbeddingsResponse;
use Cognesy\Instructor\Extras\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Extras\Http\HttpClient;

class JinaDriver implements CanVectorize
{
    public function __construct(
        protected EmbeddingsConfig $config,
        protected ?CanHandleHttp $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::make();
    }

    public function vectorize(array $input, array $options = []): EmbeddingsResponse {
        $response = $this->httpClient->handle(
            $this->getEndpointUrl(),
            $this->getRequestHeaders(),
            $this->getRequestBody($input, $options),
        );
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
            inputTokens: $response['usage']['prompt_tokens'] ?? 0,
            outputTokens: ($response['usage']['total_tokens'] ?? 0) - ($response['usage']['prompt_tokens'] ?? 0),
        );
    }
}
