<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\GeminiOAI;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIRequestAdapter;

class GeminiOAIRequestAdapter extends OpenAIRequestAdapter
{
    /**
     * Override headers to use Google header auth instead of Bearer token.
     */
    #[\Override]
    protected function toHeaders(InferenceRequest $request): array {
        return [
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
        ];
    }
}
