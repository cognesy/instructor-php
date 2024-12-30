<?php

namespace Cognesy\Instructor\Features\LLM\Drivers\Cerebras;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\LLM\Drivers\OpenAICompatible\OpenAICompatibleRequestAdapter;

class CerebrasRequestAdapter extends OpenAICompatibleRequestAdapter
{
    public function toRequestBody(
        array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = '',
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Text,
    ) : array {
        $body = parent::toRequestBody($messages, $model, $tools, $toolChoice, $responseFormat, $options, $mode);
        $body['max_completion_tokens'] = $body['max_tokens'] ?? -1;
        unset($body['max_tokens']);
        return $body;
    }
}