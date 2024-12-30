<?php

namespace Cognesy\Instructor\Features\LLM\Drivers\XAI;

use Cognesy\Instructor\Features\LLM\Drivers\OpenAICompatible\OpenAICompatibleRequestAdapter;

class XAiRequestAdapter extends OpenAICompatibleRequestAdapter
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