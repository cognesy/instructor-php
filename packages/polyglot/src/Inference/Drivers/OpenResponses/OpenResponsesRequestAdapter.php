<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenResponses;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

/**
 * Translates InferenceRequest to HTTP request for OpenResponses API.
 */
class OpenResponsesRequestAdapter implements CanTranslateInferenceRequest
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

    protected function toHeaders(InferenceRequest $request): array {
        $accept = $request->isStreamed() ? 'text/event-stream' : 'application/json';
        $headers = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => $accept,
        ];

        // Add authorization if API key is provided
        if (!empty($this->config->apiKey)) {
            $headers['Authorization'] = "Bearer {$this->config->apiKey}";
        }

        // Add OpenResponses version header if specified
        if (!empty($this->config->metadata['openResponsesVersion'] ?? '')) {
            $headers['OpenResponses-Version'] = $this->config->metadata['openResponsesVersion'];
        }

        return $headers;
    }

    protected function toUrl(InferenceRequest $request): string {
        $endpoint = $this->config->endpoint ?: '/v1/responses';
        return "{$this->config->apiUrl}{$endpoint}";
    }
}
