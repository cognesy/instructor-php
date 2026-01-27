<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenAIResponses;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

/**
 * OpenAI-specific request adapter for Responses API.
 *
 * Adds OpenAI-specific headers:
 * - Authorization: Bearer {apiKey}
 * - OpenAI-Organization: {organization} (optional)
 * - OpenAI-Project: {project} (optional)
 */
class OpenAIResponsesRequestAdapter implements CanTranslateInferenceRequest
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
        $extras = array_filter([
            'OpenAI-Organization' => $this->config->metadata['organization'] ?? '',
            'OpenAI-Project' => $this->config->metadata['project'] ?? '',
        ]);

        return array_merge([
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => $accept,
        ], $extras);
    }

    protected function toUrl(InferenceRequest $request): string {
        $endpoint = $this->config->endpoint ?: '/v1/responses';
        return "{$this->config->apiUrl}{$endpoint}";
    }
}
