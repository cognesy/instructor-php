<?php

namespace Cognesy\LLM\LLM\Drivers\Cerebras;

use Cognesy\LLM\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\LLM\LLM\Enums\Mode;

class CerebrasBodyFormat extends OpenAICompatibleBodyFormat
{
    public function map(
        array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = '',
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Text,
    ) : array {
        $body = parent::map($messages, $model, $tools, $toolChoice, $responseFormat, $options, $mode);

        $body['max_completion_tokens'] = $body['max_tokens'] ?? -1;
        unset($body['max_tokens']);
        return $body;
    }
}