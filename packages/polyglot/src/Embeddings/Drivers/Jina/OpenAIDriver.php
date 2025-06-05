<?php

namespace Cognesy\Polyglot\Embeddings\Drivers\OpenAI;

use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Embeddings\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\EmbeddingsResponse;
use Cognesy\Polyglot\LLM\Data\Usage;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

class OpenAIDriver implements CanHandleVectorization
{
    protected EmbeddingsConfig $config;
    protected HttpClient $httpClient;
    protected EventDispatcherInterface $events;

    public function __construct(
        EmbeddingsConfig $config,
        HttpClient $httpClient,
        EventDispatcherInterface $events,
    ) {
        $this->events = $events;
        $this->httpClient = $httpClient;
        $this->config = $config;
    }

    public function handle(EmbeddingsRequest $request): EmbeddingsResponse {
        $httpRequest = new HttpClientRequest(
            url: $this->getEndpointUrl(),
            method: 'POST',
            headers: $this->getRequestHeaders(),
            body: $this->getRequestBody($request),
            options: [],
        );
        $response = $this->httpClient->handle($httpRequest);
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

    protected function getRequestBody(EmbeddingsRequest $request) : array {
        $inputs = $request->inputs();
        $options = $request->options();
        $model = $request->model() ?: $this->config->model;

        if (count($inputs) > $this->config->maxInputs) {
            throw new InvalidArgumentException("Number of inputs exceeds the limit of {$this->config->maxInputs}");
        }

        return array_filter(array_merge([
            'input' => $inputs,
            'model' => $model,
            'encoding_format' => 'float',
        ], $options));
    }

    protected function toResponse(array $response) : EmbeddingsResponse {
        return new EmbeddingsResponse(
            vectors: array_map(
                callback: fn($item) => new Vector(values: $item['embedding'], id: $item['index']),
                array: $response['data']
            ),
            usage: $this->toUsage($response),
        );
    }

    protected function toUsage(array $response) : Usage {
        return new Usage(
            inputTokens: $response['usage']['prompt_tokens'] ?? 0,
            outputTokens: ($response['usage']['total_tokens'] ?? 0) - ($response['usage']['prompt_tokens'] ?? 0),
        );
    }
}