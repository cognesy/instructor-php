<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Gemini;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\BaseHttpRequestAdapter;

class GeminiRequestAdapter extends BaseHttpRequestAdapter
{
    #[\Override]
    protected function toHeaders(InferenceRequest $request): array {
        return [
            'Content-Type' => 'application/json; charset=utf-8',
            'x-goog-api-key' => $this->config->apiKey,
        ];
    }

    #[\Override]
    protected function toUrl(InferenceRequest $request): string {
        $model = $request->model() ?: $this->config->model;
        $urlParams = [];
        $endpoint = $this->endpointFor($request);

        if ($request->isStreamed()) {
            $urlParams['alt'] = 'sse';
        }

        $base = str_replace(
            search: '{model}',
            replace: $model,
            subject: "{$this->config->apiUrl}{$endpoint}"
        );

        $query = http_build_query($urlParams);
        return $query !== '' ? "$base?$query" : $base;
    }

    private function endpointFor(InferenceRequest $request): string {
        $endpoint = $this->config->endpoint !== ''
            ? $this->config->endpoint
            : '/models/{model}:generateContent';

        if (!$request->isStreamed()) {
            return $endpoint;
        }

        if (str_contains($endpoint, ':streamGenerateContent')) {
            return $endpoint;
        }

        if (str_contains($endpoint, ':generateContent')) {
            return str_replace(':generateContent', ':streamGenerateContent', $endpoint);
        }

        return $endpoint;
    }
}
