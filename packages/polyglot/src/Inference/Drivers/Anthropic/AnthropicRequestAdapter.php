<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Anthropic;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\BaseHttpRequestAdapter;

class AnthropicRequestAdapter extends BaseHttpRequestAdapter
{
    #[\Override]
    protected function toHeaders(InferenceRequest $request): array {
        return array_filter([
            'x-api-key' => $this->config->apiKey,
            'Content-Type' => 'application/json; charset=utf-8',
            'accept' => 'application/json',
            'anthropic-version' => $this->config->metadata['apiVersion'] ?? '',
            'anthropic-beta' => $this->config->metadata['beta'] ?? '',
        ]);
    }

    #[\Override]
    protected function toUrl(InferenceRequest $request): string {
        return "{$this->config->apiUrl}{$this->config->endpoint}";
    }
}
