<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\HuggingFace;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIRequestAdapter;

class HuggingFaceRequestAdapterAdapter extends OpenAIRequestAdapter
{
    #[\Override]
    protected function toUrl(InferenceRequest $request): string {
        return str_replace(
                search: array_map(fn($key) => "{" . $key . "}", array_keys($this->config->metadata)),
                replace: array_values($this->config->metadata),
                subject: "{$this->config->apiUrl}{$this->config->endpoint}"
            );
    }

    #[\Override]
    protected function toHeaders(InferenceRequest $request): array {
        return [
            'Authorization' => 'Bearer '.$this->config->apiKey,
            'Content-Type' => 'application/json; charset=utf-8',
        ];
    }
}