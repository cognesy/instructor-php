<?php

namespace Cognesy\Instructor\Features\LLM\Drivers;

use Cognesy\Instructor\Enums\Mode;

class XAiDriver extends OpenAICompatibleDriver
{
    protected function toNativeToolCall(array $message) : array {
        return [
            'role' => 'assistant',
            'content' => $message['content']
                ?? 'I\'m calling tool: ' . $message['_metadata']['tool_calls'][0]['function']['name'],
            'tool_calls' => $message['_metadata']['tool_calls']
                ?? [],
        ];
    }
}
