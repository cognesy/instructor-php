<?php

namespace Cognesy\LLM\LLM\Drivers\OpenAI;

use Cognesy\LLM\Http\Data\HttpClientRequest;
use Cognesy\LLM\LLM\Contracts\CanMapRequestBody;
use Cognesy\LLM\LLM\Contracts\ProviderRequestAdapter;
use Cognesy\LLM\LLM\Data\LLMConfig;
use Cognesy\LLM\LLM\Enums\Mode;

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
            options: ['stream' => $options['stream'] ?? false],
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