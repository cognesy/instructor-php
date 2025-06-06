<?php

namespace Cognesy\Polyglot\LLM\Drivers\Azure;

use Cognesy\Polyglot\LLM\Config\LLMConfig;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\Polyglot\LLM\InferenceRequest;

class AzureOpenAIRequestAdapter extends OpenAIRequestAdapter
{
    protected function toUrl(InferenceRequest $request): string {
        return str_replace(
                search: array_map(fn($key) => "{".$key."}", array_keys($this->config->metadata)),
                replace: array_values($this->config->metadata),
                subject: "{$this->config->apiUrl}{$this->config->endpoint}"
            ) . $this->getUrlParams($this->config);
    }

    protected function toHeaders(InferenceRequest $request): array {
        return [
            'Api-Key' => $this->config->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    // INTERNAL //////////////////////////////////////////////

    protected function getUrlParams(LLMConfig $config): string {
        $params = array_filter([
            'api-version' => $config->metadata['apiVersion'] ?? '',
        ]);
        if (!empty($params)) {
            return '?' . http_build_query($params);
        }
        return '';
    }
}