<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenResponses;

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\Enums\MessageType;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Data\ReplayEnvelope;
use Cognesy\Polyglot\Inference\Drivers\MessageMapper;

/**
 * Formats messages for OpenResponses API.
 *
 * OpenResponses uses an "items" format where messages are converted to items:
 * - User messages → message items with role: "user"
 * - Assistant messages → message items with role: "assistant"
 * - Tool calls → function_call items
 * - Tool results → function_call_output items
 */
class OpenResponsesMessageFormat implements CanMapMessages
{
    #[\Override]
    public function map(Messages $messages): array
    {
        return MessageMapper::flatMap($messages, $this->mapMessageToItems(...));
    }

    // INTERNAL /////////////////////////////////////////////

    /** @return array[] */
    protected function mapMessageToItems(Message $message): array
    {
        return match (true) {
            $message->isAssistant() => $this->toAssistantItems($message),
            $message->type() === MessageType::ToolResult => [$this->toFunctionCallOutputItem($message)],
            default => [$this->toMessageItem($message)],
        };
    }

    /** @return array[] */
    private function toAssistantItems(Message $message): array
    {
        $replay = ReplayEnvelope::fromMessage($message, OpenResponsesReplay::OWNER);
        $items = [];
        foreach ($message->parts() as $position => $part) {
            $item = match (true) {
                $part->isTextPart() && $part->isEmpty() => null,
                $part->isTextPart() => $this->toAssistantTextItem($part),
                $part->isReasoningPart() => OpenResponsesReplay::reasoningItem($replay, $position, $part),
                $part->isToolCallPart() => $this->toFunctionCallItem($part),
                default => null,
            };
            if ($item !== null) {
                $items[] = $item;
            }
        }
        return $items;
    }

    private function toAssistantTextItem(ContentPart $part): array
    {
        return [
            'type' => 'message',
            'role' => 'assistant',
            'content' => [[
                'type' => 'output_text',
                'text' => $part->toString(),
            ]],
        ];
    }

    private function toFunctionCallItem(ContentPart $part): ?array
    {
        $toolCall = $part->toToolCall();
        if ($toolCall === null) {
            return null;
        }
        return [
            'type' => 'function_call',
            'call_id' => $toolCall->idString(),
            'name' => $toolCall->name(),
            'arguments' => $toolCall->argsAsJson(),
        ];
    }

    /**
     * Convert a regular message to a message item.
     */
    protected function toMessageItem(Message $message): array
    {
        return [
            'type' => 'message',
            'role' => $message->role()->value,
            'content' => $this->toContentItems($message->content(), $message->role()->value),
        ];
    }

    /**
     * Convert a tool result message to function_call_output item.
     */
    protected function toFunctionCallOutputItem(Message $message): array
    {
        return [
            'type' => 'function_call_output',
            'call_id' => $message->toolResult()->callIdString(),
            'output' => $message->toolResult()->content(),
        ];
    }

    /**
     * Convert content to content items array.
     */
    protected function toContentItems(Content $content, string $role = 'user'): array
    {
        $textType = $role === 'assistant' ? 'output_text' : 'input_text';

        if (!$content->isComposite()) {
            return [[
                'type' => $textType,
                'text' => $content->toString(),
            ]];
        }

        $items = [];
        foreach ($content->partsList()->all() as $part) {
            $items[] = $this->contentPartToItem($part, $textType);
        }

        return $items;
    }

    protected function contentPartToItem(ContentPart $part, string $textType): array
    {
        return match ($part->type()) {
            'text' => [
                'type' => $textType,
                'text' => $part->toString(),
            ],
            'image_url' => [
                'type' => 'input_image',
                'image_url' => $part->get('image_url', ''),
            ],
            default => $part->toArray(),
        };
    }
}
