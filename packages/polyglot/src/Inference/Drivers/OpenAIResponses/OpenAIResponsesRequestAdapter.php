<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenAIResponses;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\BaseHttpRequestAdapter;

/**
 * OpenAI-specific request adapter for Responses API.
 *
 * Adds OpenAI-specific headers:
 * - Authorization: Bearer {apiKey}
 * - OpenAI-Organization: {organization} (optional)
 * - OpenAI-Project: {project} (optional)
 */
class OpenAIResponsesRequestAdapter extends BaseHttpRequestAdapter
{
    #[\Override]
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

    #[\Override]
    protected function toUrl(InferenceRequest $request): string {
        $endpoint = $this->config->endpoint ?: '/v1/responses';
        return "{$this->config->apiUrl}{$endpoint}";
    }
}
