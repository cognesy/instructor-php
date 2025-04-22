<?php

namespace Cognesy\Polyglot\Embeddings\Drivers;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Contracts\CanVectorize;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Embeddings\EmbeddingsResponse;
use Cognesy\Polyglot\LLM\Data\Usage;
use Cognesy\Utils\Events\EventDispatcher;

class CohereDriver implements CanVectorize
{
    public function __construct(
        protected EmbeddingsConfig      $config,
        protected ?CanHandleHttpRequest $httpClient = null,
        protected ?EventDispatcher      $events = null,
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
        return $this->toResponse(json_decode($response->body(), true));
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
            usage: $this->makeUsage($response),
        );
    }

    protected function makeUsage(array $response) : Usage {
        return new Usage(
            inputTokens: $response['meta']['billed_units']['input_tokens'] ?? 0,
            outputTokens: $response['meta']['billed_units']['output_tokens'] ?? 0,
        );
    }
}
