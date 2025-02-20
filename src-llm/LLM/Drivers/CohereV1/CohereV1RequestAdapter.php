<?php

namespace Cognesy\LLM\LLM\Drivers\CohereV1;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\LLM\Http\Data\HttpClientRequest;
use Cognesy\LLM\LLM\Contracts\CanMapRequestBody;
use Cognesy\LLM\LLM\Contracts\ProviderRequestAdapter;
use Cognesy\LLM\LLM\Data\LLMConfig;

class CohereV1RequestAdapter implements ProviderRequestAdapter
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
        return [
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json',
        ];
    }

    protected function toUrl(string $model = '', bool $stream = false): string {
        return "{$this->config->apiUrl}{$this->config->endpoint}";
    }
}