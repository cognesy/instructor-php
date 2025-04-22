<?php

namespace Cognesy\Polyglot\LLM\Drivers\Azure;

use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIRequestAdapter;

class AzureOpenAIRequestAdapter extends OpenAIRequestAdapter
{
    protected function toUrl(string $model = '', bool $stream = false): string {
        return str_replace(
                search: array_map(fn($key) => "{".$key."}", array_keys($this->config->metadata)),
                replace: array_values($this->config->metadata),
                subject: "{$this->config->apiUrl}{$this->config->endpoint}"
            ) . $this->getUrlParams($this->config);
    }

    protected function toHeaders(): array {
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