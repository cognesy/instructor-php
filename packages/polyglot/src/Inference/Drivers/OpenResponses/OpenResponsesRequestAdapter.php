<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenResponses;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\BaseHttpRequestAdapter;

/**
 * Translates InferenceRequest to HTTP request for OpenResponses API.
 */
class OpenResponsesRequestAdapter extends BaseHttpRequestAdapter
{
    #[\Override]
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

    #[\Override]
    protected function toUrl(InferenceRequest $request): string {
        $endpoint = $this->config->endpoint ?: '/v1/responses';
        return "{$this->config->apiUrl}{$endpoint}";
    }
}
