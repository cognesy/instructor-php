<?php

namespace Cognesy\Polyglot\LLM\Drivers\HuggingFace;

use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIRequestAdapter;

class HuggingFaceRequestAdapter extends OpenAIRequestAdapter
{
    protected function toUrl(string $model = '', bool $stream = false): string {
        return str_replace(
                search: array_map(fn($key) => "{" . $key . "}", array_keys($this->config->metadata)),
                replace: array_values($this->config->metadata),
                subject: "{$this->config->apiUrl}{$this->config->endpoint}"
            );
    }

    protected function toHeaders(): array {
        return [
            'Authorization' => 'Bearer '.$this->config->apiKey,
            'Content-Type' => 'application/json',
        ];
    }
}