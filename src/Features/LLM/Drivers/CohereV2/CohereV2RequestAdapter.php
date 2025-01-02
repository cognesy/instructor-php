<?php

namespace Cognesy\Instructor\Features\LLM\Drivers\CohereV2;

use Cognesy\Instructor\Features\LLM\Drivers\OpenAI\OpenAIRequestAdapter;

class CohereV2RequestAdapter extends OpenAIRequestAdapter
{
    protected function toHeaders(): array {
        $optional = [
            'X-Client-Name' => $this->config->metadata['client_name'] ?? '',
        ];
        return array_merge([
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json',
        ], $optional);
    }
}