<?php

namespace Cognesy\Polyglot\Inference\Drivers\CohereV1;

use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

class CohereV1RequestAdapter implements ProviderRequestAdapter
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

    protected function toHeaders(InferenceRequest $request): array {
        return [
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json',
        ];
    }

    protected function toUrl(InferenceRequest $request): string {
        return "{$this->config->apiUrl}{$this->config->endpoint}";
    }
}