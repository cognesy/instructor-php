<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Anthropic;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

class AnthropicRequestAdapter implements CanTranslateInferenceRequest
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapRequestBody $bodyFormat,
    ) {}

    #[\Override]
    public function toHttpRequest(InferenceRequest $request): HttpRequest {
        return new HttpRequest(
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
            'Content-Type' => 'application/json; charset=utf-8',
            'accept' => 'application/json',
            'anthropic-version' => $this->config->metadata['apiVersion'] ?? '',
            'anthropic-beta' => $this->config->metadata['beta'] ?? '',
        ]);
    }

    protected function toUrl(InferenceRequest $request): string {
        return "{$this->config->apiUrl}{$this->config->endpoint}";
    }
}