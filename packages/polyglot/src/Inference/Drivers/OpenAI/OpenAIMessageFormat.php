<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenAI;

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCalls;
use Cognesy\Messages\Enums\MessageType;
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
        return match ($message->type()) {
            MessageType::AssistantToolCalls => $this->toNativeToolCall($message),
            MessageType::ToolResult => $this->toNativeToolResult($message),
            default => $this->toNativeTextMessage($message),
        };
    }

    protected function toNativeTextMessage(Message $message): array
    {
        return array_filter([
            'role' => $message->role()->value,
            'content' => $this->toNativeContent($message->content()),
            'name' => $message->name(),
        ], $this->shouldKeepField(...));
    }

    protected function toNativeToolCall(Message $message): array
    {
        return array_filter([
            'role' => 'assistant',
            'content' => $this->toNativeContent($message->content()),
            'tool_calls' => $this->toolCallsToOpenAI($message->toolCalls()),
        ], $this->shouldKeepField(...));
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

    protected function toNativeContent(Content $content): string|array
    {
        if (!$content->isComposite()) {
            return $content->toString();
        }

        return $content->partsList()->map($this->toNativeContentPart(...));
    }

    protected function toNativeContentPart(ContentPart $contentPart): array
    {
        return match ($contentPart->type()) {
            'text' => [
                'type' => 'text',
                'text' => $contentPart->toString(),
            ],
            'image_url' => [
                'type' => 'image_url',
                'image_url' => $this->toNativeImageUrl($contentPart),
            ],
            default => $contentPart->toArray(),
        };
    }

    /** @return array<string, mixed> */
    protected function toNativeImageUrl(ContentPart $contentPart): array
    {
        $imageUrl = $contentPart->get('image_url', []);
        $native = match (true) {
            is_array($imageUrl) => $imageUrl,
            is_string($imageUrl) => ['url' => $imageUrl],
            default => [],
        };

        $detail = $contentPart->get('detail');
        if (is_string($detail) && $detail !== '') {
            $native['detail'] = $detail;
        }

        return $native;
    }

    protected function shouldKeepField(mixed $value): bool
    {
        return match (true) {
            $value === '',
            $value === [],
            $value === null => false,
            default => true,
        };
    }
}
