<?php

namespace Cognesy\Instructor\Extras\Embeddings\Drivers;

use Cognesy\Instructor\Extras\Embeddings\Contracts\CanVectorize;
use Cognesy\Instructor\Extras\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Instructor\Extras\Embeddings\Data\Vector;
use Cognesy\Instructor\Extras\Embeddings\EmbeddingsResponse;
use Cognesy\Instructor\Features\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Features\Http\HttpClient;

class CohereDriver implements CanVectorize
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
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json',
        ];
    }

    protected function getRequestBody(array $input, array $options) : array {
        $options['input_type'] = $options['input_type'] ?? 'search_document';
        return array_filter(array_merge([
                'texts' => $input,
                'model' => $this->config->model,
                'embedding_types' => ['float'],
                'truncate' => 'END',
        ], $options));
    }

    protected function toResponse(array $response) : EmbeddingsResponse {
        $vectors = [];
        foreach ($response['embeddings']['float'] as $key => $item) {
            $vectors[] = new Vector(values: $item, id: $key);
        }
        return new EmbeddingsResponse(
            vectors: $vectors,
            inputTokens: $response['meta']['billed_units']['input_tokens'] ?? 0,
            outputTokens: 0,
        );
    }
}
