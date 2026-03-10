<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenAI;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Contracts\MessageMapper;
use Cognesy\Utils\Json\Json;

class OpenAIMessageFormat implements CanMapMessages
{
    #[\Override]
    public function map(Messages $messages): array
    {
        return (new MessageMapper($this->mapMessage(...)))->map($messages);
    }

    // INTERNAL /////////////////////////////////////////////

    protected function mapMessage(Message $message): array
    {
        return match (true) {
            $message->isAssistant() && $message->hasToolCalls() => $this->toNativeToolCall($message),
            $message->isTool() && $message->hasToolResult() => $this->toNativeToolResult($message),
            default => $this->toNativeTextMessage($message),
        };
    }

    protected function toNativeTextMessage(Message $message): array
    {
        return array_filter([
            'role' => $message->role()->value,
            'content' => $message->content()->toString(),
            'name' => $message->name(),
        ]);
    }

    protected function toNativeToolCall(Message $message): array
    {
        return [
            'role' => 'assistant',
            'tool_calls' => $this->toolCallsToOpenAI($message->toolCalls()),
        ];
    }

    protected function toNativeToolResult(Message $message): array
    {
        return [
            'role' => 'tool',
            'tool_call_id' => $message->toolResult()->callIdString(),
            'content' => $message->content()->toString(),
        ];
    }

    protected function toolCallsToOpenAI(ToolCalls $toolCalls): array
    {
        return $toolCalls->map(fn (ToolCall $tc) => [
            'id' => $tc->idString(),
            'type' => 'function',
            'function' => [
                'name' => $tc->name(),
                'arguments' => Json::encode($tc->arguments()),
            ],
        ]);
    }
}
