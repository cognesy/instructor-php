<?php
namespace Cognesy\Instructor\Extras\Embeddings\Drivers;

use Cognesy\Instructor\Extras\Embeddings\Contracts\CanVectorize;
use Cognesy\Instructor\Extras\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Instructor\Extras\Embeddings\Data\Vector;
use Cognesy\Instructor\Extras\Embeddings\EmbeddingsResponse;
use Cognesy\Instructor\Extras\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Extras\Http\HttpClient;

class AzureOpenAIDriver implements CanVectorize
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
        return $this->toResponse(json_decode($response->getBody()->getContents(), true));
    }

    // INTERNAL /////////////////////////////////////////////////

    protected function getEndpointUrl(): string {
        return str_replace(
                search: array_map(fn($key) => "{".$key."}", array_keys($this->config->metadata)),
                replace: array_values($this->config->metadata),
                subject: "{$this->config->apiUrl}{$this->config->endpoint}"
            ) . $this->getUrlParams();
    }

    protected function getUrlParams(): string {
        $params = array_filter([
            'api-version' => $this->config->metadata['apiVersion'] ?? '',
        ]);
        if (!empty($params)) {
            return '?' . http_build_query($params);
        }
        return '';
    }

    protected function getRequestHeaders(): array {
        return [
            'Api-Key' => $this->config->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    protected function getRequestBody(array $input, array $options) : array {
        return array_filter(array_merge([
            'input' => $input,
            'model' => $this->config->model,
            'encoding_format' => 'float',
        ], $options));
    }

    protected function toResponse(array $response) : EmbeddingsResponse {
        return new EmbeddingsResponse(
            vectors: array_map(
                callback: fn($item) => new Vector(values: $item['embedding'], id: $item['index']),
                array: $response['data']
            ),
            inputTokens: $response['usage']['prompt_tokens'] ?? 0,
            outputTokens: ($response['usage']['total_tokens'] ?? 0) - ($response['usage']['prompt_tokens'] ?? 0),
        );
    }
}