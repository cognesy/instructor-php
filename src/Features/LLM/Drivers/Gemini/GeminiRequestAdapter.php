<?php

namespace Cognesy\Instructor\Features\LLM\Drivers\Gemini;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\Http\Data\HttpClientRequest;
use Cognesy\Instructor\Features\LLM\Contracts\CanMapRequestBody;
use Cognesy\Instructor\Features\LLM\Contracts\ProviderRequestAdapter;
use Cognesy\Instructor\Features\LLM\Data\LLMConfig;

class GeminiRequestAdapter implements ProviderRequestAdapter
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
        return [
            'Content-Type' => 'application/json',
        ];
    }

    protected function toUrl(string $model = '', bool $stream = false): string {
        $model = $model ?: $this->config->model;
        $urlParams = ['key' => $this->config->apiKey];

        if ($stream) {
            $this->config->endpoint = '/models/{model}:streamGenerateContent';
            $urlParams['alt'] = 'sse';
        } else {
            $this->config->endpoint = '/models/{model}:generateContent';
        }

        return str_replace(
            search: "{model}",
            replace: $model,
            subject: "{$this->config->apiUrl}{$this->config->endpoint}?" . http_build_query($urlParams)
        );
    }
}