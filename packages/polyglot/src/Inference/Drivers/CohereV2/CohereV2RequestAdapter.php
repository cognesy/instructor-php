<?php

namespace Cognesy\Polyglot\Inference\Drivers\CohereV2;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIRequestAdapter;

class CohereV2RequestAdapter extends OpenAIRequestAdapter
{
    protected function toHeaders(InferenceRequest $request): array {
        $optional = [
            'X-Client-Name' => $this->config->metadata['client_name'] ?? '',
        ];
        return array_merge([
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json',
        ], $optional);
    }
}