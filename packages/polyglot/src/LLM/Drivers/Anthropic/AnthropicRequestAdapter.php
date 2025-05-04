<?php

namespace Cognesy\Polyglot\LLM\Drivers\Anthropic;

use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\LLM\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\LLM\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

class AnthropicRequestAdapter implements ProviderRequestAdapter
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
        OutputMode $mode
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
        return array_filter([
            'x-api-key' => $this->config->apiKey,
            'content-type' => 'application/json',
            'accept' => 'application/json',
            'anthropic-version' => $this->config->metadata['apiVersion'] ?? '',
            'anthropic-beta' => $this->config->metadata['beta'] ?? '',
        ]);
    }

    protected function toUrl(string $model = '', bool $stream = false): string {
        return "{$this->config->apiUrl}{$this->config->endpoint}";
    }
}