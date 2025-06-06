<?php

namespace Cognesy\Polyglot\LLM\Drivers\Gemini;

use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\LLM\Config\LLMConfig;
use Cognesy\Polyglot\LLM\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\LLM\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\LLM\InferenceRequest;

class GeminiRequestAdapter implements ProviderRequestAdapter
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapRequestBody $bodyFormat,
    ) {}

    public function toHttpClientRequest(InferenceRequest $request): HttpClientRequest {
        return new HttpClientRequest(
            url: $this->toUrl($request),
            method: 'POST',
            headers: $this->toHeaders($request),
            body: $this->bodyFormat->toRequestBody($request),
            options: ['stream' => $options['stream'] ?? false],
        );
    }

    protected function toHeaders(InferenceRequest $request): array {
        return [
            'Content-Type' => 'application/json',
        ];
    }

    protected function toUrl(InferenceRequest $request): string {
        $model = $request->model() ?: $this->config->defaultModel;
        $urlParams = ['key' => $this->config->apiKey];

        if ($request->isStreamed()) {
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