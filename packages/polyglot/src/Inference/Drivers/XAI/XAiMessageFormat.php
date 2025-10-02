<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\XAI;

use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;

class XAiMessageFormat extends OpenAIMessageFormat
{
    #[\Override]
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