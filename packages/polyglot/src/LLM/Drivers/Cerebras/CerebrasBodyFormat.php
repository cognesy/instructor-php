<?php

namespace Cognesy\Polyglot\LLM\Drivers\Cerebras;

use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

class CerebrasBodyFormat extends OpenAICompatibleBodyFormat
{
    public function map(
        array        $messages = [],
        string       $model = '',
        array        $tools = [],
        string|array $toolChoice = '',
        array        $responseFormat = [],
        array        $options = [],
        OutputMode   $mode = OutputMode::Text,
    ) : array {
        $options = array_merge($this->config->options, $options);

        $body = parent::map($messages, $model, $tools, $toolChoice, $responseFormat, $options, $mode);

        $body['max_completion_tokens'] = $body['max_tokens'] ?? -1;
        unset($body['max_tokens']);

        return $body;
    }
}