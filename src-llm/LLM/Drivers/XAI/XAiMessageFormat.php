<?php

namespace Cognesy\LLM\LLM\Drivers\XAI;

use Cognesy\LLM\LLM\Drivers\OpenAI\OpenAIMessageFormat;

class XAiMessageFormat extends OpenAIMessageFormat
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