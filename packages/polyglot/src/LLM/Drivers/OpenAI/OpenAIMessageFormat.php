<?php

namespace Cognesy\Polyglot\LLM\Drivers\OpenAI;

use Cognesy\Polyglot\LLM\Contracts\CanMapMessages;

class OpenAIMessageFormat implements CanMapMessages
{
    public function map(array $messages) : array {
        $list = [];
        foreach ($messages as $message) {
            $nativeMessage = $this->mapMessage($message);
            if (empty($nativeMessage)) {
                continue;
            }
            $list[] = $nativeMessage;
        }
        return $list;
    }

    protected function mapMessage(array $message) : array {
        return match(true) {
            ($message['role'] ?? '') === 'assistant' && !empty($message['_metadata']['tool_calls'] ?? []) => $this->toNativeToolCall($message),
            ($message['role'] ?? '') === 'tool' => $this->toNativeToolResult($message),
            default => $message,
        };
    }

    protected function toNativeToolCall(array $message) : array {
        return [
            'role' => 'assistant',
            'tool_calls' => $message['_metadata']['tool_calls'] ?? [],
        ];
    }

    protected function toNativeToolResult(array $message) : array {
        return [
            'role' => 'tool',
            'tool_call_id' => $message['_metadata']['tool_call_id'] ?? '',
            'content' => $message['content'] ?? '',
        ];
    }
}