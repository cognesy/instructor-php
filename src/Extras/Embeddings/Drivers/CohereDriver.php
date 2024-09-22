<?php

namespace Cognesy\Instructor\Extras\Embeddings\Drivers;

use Cognesy\Instructor\Extras\Embeddings\Contracts\CanVectorize;
use Cognesy\Instructor\Extras\Embeddings\EmbeddingsConfig;
use Cognesy\Instructor\Extras\Embeddings\EmbeddingsResponse;
use Cognesy\Instructor\Extras\Embeddings\Vector;
use GuzzleHttp\Client;

class CohereDriver implements CanVectorize
{
    public function __construct(
        protected Client $client,
        protected EmbeddingsConfig $config
    ) {}

    public function vectorize(array $input, array $options = []) : EmbeddingsResponse {
        $response = $this->client->post($this->getEndpointUrl(), [
            'headers' => $this->getRequestHeaders(),
            'json' => $this->getRequestBody($input, $options),
        ]);
        return $this->toResponse(json_decode($response->getBody()->getContents(), true));
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
