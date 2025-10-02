<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Gemini;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

class GeminiRequestAdapter implements CanTranslateInferenceRequest
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
        return [
            'Content-Type' => 'application/json; charset=utf-8',
            'x-goog-api-key' => $this->config->apiKey,
        ];
    }

    protected function toUrl(InferenceRequest $request): string {
        $model = $request->model() ?: $this->config->model;
        $urlParams = [];

        if ($request->isStreamed()) {
            $this->config->endpoint = '/models/{model}:streamGenerateContent';
            $urlParams['alt'] = 'sse';
        } else {
            $this->config->endpoint = '/models/{model}:generateContent';
        }

        $base = str_replace(
            search: '{model}',
            replace: $model,
            subject: "{$this->config->apiUrl}{$this->config->endpoint}"
        );

        $query = http_build_query($urlParams);
        return $query !== '' ? "$base?$query" : $base;
    }
}
