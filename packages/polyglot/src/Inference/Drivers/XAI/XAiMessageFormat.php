<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\XAI;

use Cognesy\Messages\Message;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;

class XAiMessageFormat extends OpenAIMessageFormat
{
    #[\Override]
    protected function toNativeToolCall(Message $message): array
    {
        $firstToolCall = $message->toolCalls()->first();

        return [
            'role' => 'assistant',
            'content' => $message->content()->toString() !== ''
                ? $message->content()->toString()
                : 'I\'m calling tool: ' . ($firstToolCall?->name() ?? ''),
            'tool_calls' => $this->toolCallsToOpenAI($message->toolCalls()),
        ];
    }
}
