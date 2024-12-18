<?php

namespace Cognesy\Instructor\Features\LLM\Drivers;

use Cognesy\Instructor\Enums\Mode;

class CerebrasDriver extends OpenAICompatibleDriver
{
    public function getRequestBody(
        array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = '',
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Text,
    ) : array {
        $body = parent::getRequestBody($messages, $model, $tools, $toolChoice, $responseFormat, $options, $mode);
        $body['max_completion_tokens'] = $body['max_tokens'] ?? -1;
        unset($body['max_tokens']);
        return $body;
    }
}