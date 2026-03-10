<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Anthropic;

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\Enums\MessageType;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Contracts\MessageMapper;
use Cognesy\Utils\Str;

class AnthropicMessageFormat implements CanMapMessages
{
    /** @var array<string, string> */
    private array $roles = [
        'user' => 'user',
        'assistant' => 'assistant',
        'system' => 'user',
        'developer' => 'user',
        'tool' => 'user',
    ];

    #[\Override]
    public function map(Messages $messages): array
    {
        return (new MessageMapper($this->mapMessage(...)))->map($messages);
    }

    // INTERNAL /////////////////////////////////////////////

    private function mapMessage(Message $message): array
    {
        return match ($message->type()) {
            MessageType::AssistantToolCalls => $this->toNativeToolCall($message),
            MessageType::ToolResult => $this->toNativeToolResult($message),
            default => $this->toNativeTextMessage($message),
        };
    }

    private function toNativeTextMessage(Message $message): array
    {
        return [
            'role' => $this->mapRole($message->role()->value),
            'content' => $this->toNativeContent($message->content()),
        ];
    }

    private function mapRole(string $role): string
    {
        return $this->roles[$role] ?? $role;
    }

    private function toNativeContent(Content $content): string|array
    {
        if (!$content->isComposite()) {
            return $content->toString();
        }

        $transformed = [];
        foreach ($content->partsList()->all() as $contentPart) {
            $part = $this->contentPartToNative($contentPart);
            if ($contentPart->has('cache_control')) {
                $part['cache_control'] = ['type' => 'ephemeral'];
            }
            $transformed[] = $part;
        }

        return $transformed;
    }

    private function contentPartToNative(ContentPart $contentPart): array
    {
        $type = $contentPart->type();

        return match ($type) {
            'text' => $this->toNativeTextContent($contentPart),
            'image_url' => $this->toNativeImage($contentPart),
            default => $contentPart->toArray(),
        };
    }

    private function toNativeTextContent(ContentPart $contentPart): array
    {
        return [
            'type' => 'text',
            'text' => $contentPart->toString(),
        ];
    }

    private function toNativeImage(ContentPart $contentPart): array
    {
        $imageUrl = $contentPart->get('image_url', []);
        $url = match (true) {
            is_array($imageUrl) => $imageUrl['url'] ?? '',
            is_string($imageUrl) => $imageUrl,
            default => '',
        };

        return [
            'type' => 'image',
            'source' => [
                'type' => 'base64',
                'media_type' => Str::between($url, 'data:', ';base64,'),
                'data' => Str::after($url, ';base64,'),
            ],
        ];
    }

    private function toNativeToolCall(Message $message): array
    {
        $content = [];
        $textContent = $this->toNativeContent($message->content());
        $content = match (true) {
            $textContent === '' => $content,
            is_string($textContent) => [[
                'type' => 'text',
                'text' => $textContent,
            ]],
            default => $textContent,
        };

        return [
            'role' => 'assistant',
            'content' => [
                ...$content,
                ...$message->toolCalls()->map(
                    fn (ToolCall $tc) => array_filter([
                        'type' => 'tool_use',
                        'id' => $tc->idString(),
                        'name' => $tc->name(),
                        'input' => $tc->arguments(),
                    ]),
                ),
            ],
        ];
    }

    private function toNativeToolResult(Message $message): array
    {
        return [
            'role' => 'user',
            'content' => [array_filter([
                'type' => 'tool_result',
                'tool_use_id' => $message->toolResult()->callIdString(),
                'content' => $message->content()->toString(),
            ])],
        ];
    }
}
