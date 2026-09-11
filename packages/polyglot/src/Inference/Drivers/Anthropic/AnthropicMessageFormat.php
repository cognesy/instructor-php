<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Anthropic;

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\Enums\MessageType;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Data\ReplayEnvelope;
use Cognesy\Polyglot\Inference\Drivers\MessageMapper;
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
        return match (true) {
            $message->isAssistant() => $this->toNativeAssistantMessage($message),
            $message->type() === MessageType::ToolResult => $this->toNativeToolResult($message),
            default => $this->toNativeTextMessage($message),
        };
    }

    private function toNativeAssistantMessage(Message $message): array
    {
        $replay = ReplayEnvelope::fromMessage($message, AnthropicReplay::OWNER);
        $content = [];
        foreach ($message->parts() as $position => $part) {
            $native = $this->assistantPartToNative($part, AnthropicReplay::metadata($replay, $position, $part));
            if ($native !== null) {
                $content[] = $native;
            }
        }
        return [
            'role' => 'assistant',
            'content' => $content,
        ];
    }

    /** @param null|array{kind:string,signature?:string,data?:string} $replay */
    private function assistantPartToNative(ContentPart $part, ?array $replay): ?array
    {
        return match (true) {
            $part->isTextPart() && trim($part->toString()) === '' => null,
            $part->isTextPart() => $this->assistantTextPartToNative($part),
            $part->isReasoningPart() => $this->reasoningPartToNative($part, $replay),
            $part->isToolCallPart() => $this->toolCallPartToNative($part),
            default => null,
        };
    }

    private function assistantTextPartToNative(ContentPart $part): array
    {
        $native = [
            'type' => 'text',
            'text' => $part->toString(),
        ];
        if ($part->has('cache_control')) {
            $native['cache_control'] = $part->get('cache_control');
        }
        return $native;
    }

    /** @param null|array{kind:string,signature?:string,data?:string} $replay */
    private function reasoningPartToNative(ContentPart $part, ?array $replay): ?array
    {
        if (($replay['kind'] ?? '') === 'redacted_thinking' && isset($replay['data'])) {
            return [
                'type' => 'redacted_thinking',
                'data' => $replay['data'],
            ];
        }
        if (($replay['kind'] ?? '') !== 'thinking' || !isset($replay['signature'])) {
            return null;
        }
        return [
            'type' => 'thinking',
            'thinking' => $part->reasoningText(),
            'signature' => $replay['signature'],
        ];
    }

    private function toolCallPartToNative(ContentPart $part): ?array
    {
        $toolCall = $part->toToolCall();
        if ($toolCall === null) {
            return null;
        }
        return array_filter([
            'type' => 'tool_use',
            'id' => $toolCall->idString(),
            'name' => $toolCall->name(),
            'input' => $toolCall->arguments(),
            'cache_control' => $part->get('cache_control'),
        ], static fn(mixed $value): bool => $value !== '' && $value !== null);
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
            if ($contentPart->isTextPart() && trim($contentPart->toString()) === '') {
                continue;
            }
            $part = $this->contentPartToNative($contentPart);
            if ($contentPart->has('cache_control')) {
                $part['cache_control'] = $contentPart->get('cache_control');
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

    private function toNativeToolResult(Message $message): array
    {
        return [
            'role' => 'user',
            'content' => [array_filter([
                'type' => 'tool_result',
                'tool_use_id' => $message->toolResult()->callIdString(),
                'content' => $message->toolResult()->content(),
                'cache_control' => $message->parts()->filter(static fn(ContentPart $part): bool => $part->isToolResultPart())->first()?->get('cache_control'),
            ], static fn (mixed $value): bool => (bool) $value)],
        ];
    }
}
