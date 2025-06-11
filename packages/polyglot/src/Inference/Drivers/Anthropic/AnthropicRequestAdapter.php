<?php

namespace Cognesy\Polyglot\Inference\Drivers\Anthropic;

use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

class AnthropicRequestAdapter implements ProviderRequestAdapter
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
            options: ['stream' => $request->isStreamed()],
        );
    }

    // INTERNAL /////////////////////////////////////////////

    protected function toHeaders(InferenceRequest $request): array {
        return array_filter([
            'x-api-key' => $this->config->apiKey,
            'content-type' => 'application/json',
            'accept' => 'application/json',
            'anthropic-version' => $this->config->metadata['apiVersion'] ?? '',
            'anthropic-beta' => $this->config->metadata['beta'] ?? '',
        ]);
    }

    protected function toUrl(InferenceRequest $request): string {
        return "{$this->config->apiUrl}{$this->config->endpoint}";
    }
}