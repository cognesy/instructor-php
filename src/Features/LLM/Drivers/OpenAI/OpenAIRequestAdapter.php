<?php

namespace Cognesy\Instructor\Features\LLM\Drivers\OpenAI;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\Http\Data\HttpClientRequest;
use Cognesy\Instructor\Features\LLM\Contracts\CanMapRequestBody;
use Cognesy\Instructor\Features\LLM\Contracts\ProviderRequestAdapter;
use Cognesy\Instructor\Features\LLM\Data\LLMConfig;

class OpenAIRequestAdapter implements ProviderRequestAdapter
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapRequestBody $bodyFormat,
    ) {}

    public function toHttpClientRequest(
        array $messages,
        string $model,
        array $tools,
        array|string $toolChoice,
        array $responseFormat,
        array $options,
        Mode $mode
    ): HttpClientRequest {
        return new HttpClientRequest(
            url: $this->toUrl($model, $options['stream'] ?? false),
            method: 'POST',
            headers: $this->toHeaders(),
            body: $this->bodyFormat->map($messages, $model, $tools, $toolChoice, $responseFormat, $options, $mode),
            options: [],
        );
    }

    protected function toHeaders(): array {
        $extras = array_filter([
            "OpenAI-Organization" => $this->config->metadata['organization'] ?? '',
            "OpenAI-Project" => $this->config->metadata['project'] ?? '',
        ]);
        return array_merge([
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $extras);
    }

    protected function toUrl(string $model = '', bool $stream = false): string {
        return "{$this->config->apiUrl}{$this->config->endpoint}";
    }
}