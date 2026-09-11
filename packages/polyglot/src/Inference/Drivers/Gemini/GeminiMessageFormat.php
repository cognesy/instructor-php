<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Gemini;

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\Enums\MessageType;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Data\ReplayEnvelope;
use Cognesy\Polyglot\Inference\Drivers\MessageMapper;
use Cognesy\Utils\Str;

class GeminiMessageFormat implements CanMapMessages
{
    /** @var array<string, string> */
    private array $roles = [
        'user' => 'user',
        'assistant' => 'model',
        'system' => 'user',
        'developer' => 'user',
        'tool' => 'tool',
    ];

    #[\Override]
    public function map(Messages $messages): array
    {
        return (new MessageMapper($this->mapMessage(...)))->map($messages);
    }

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
        $replay = ReplayEnvelope::fromMessage($message, GeminiReplay::OWNER);
        $parts = [];
        foreach ($message->parts() as $position => $part) {
            $native = $this->assistantPartToNative($part, GeminiReplay::metadata($replay, $position, $part));
            if ($native !== null) {
                $parts[] = $native;
            }
        }
        return [
            'role' => 'model',
            'parts' => $parts,
        ];
    }

    /** @param null|array{thoughtSignature:string} $replay */
    private function assistantPartToNative(ContentPart $part, ?array $replay): ?array
    {
        $native = match (true) {
            $part->isTextPart() && $part->isEmpty() => null,
            $part->isTextPart() => ['text' => $part->toString()],
            $part->isReasoningPart() && $replay !== null => [
                'text' => $part->reasoningText(),
                'thought' => true,
            ],
            $part->isReasoningPart() => null,
            $part->isToolCallPart() => $this->toolCallPartToNative($part),
            default => null,
        };
        if ($native === null || $replay === null) {
            return $native;
        }
        $native['thoughtSignature'] = $replay['thoughtSignature'];
        return $native;
    }

    private function toolCallPartToNative(ContentPart $part): ?array
    {
        $toolCall = $part->toToolCall();
        if ($toolCall === null) {
            return null;
        }
        return [
            'functionCall' => [
                'name' => $toolCall->name(),
                'args' => $toolCall->arguments(),
            ],
        ];
    }

    private function toNativeTextMessage(Message $message): array
    {
        return [
            'role' => $this->mapRole($message->role()->value),
            'parts' => $this->toNativeContentParts($message->content()),
        ];
    }

    private function toNativeToolResult(Message $message): array
    {
        $toolName = $message->toolResult()->toolName() ?? '';

        return [
            'role' => 'user',
            'parts' => [[
                'functionResponse' => [
                    'name' => $toolName,
                    'response' => [
                        'name' => $toolName,
                        'content' => $message->toolResult()->content(),
                    ],
                ],
            ]],
        ];
    }

    protected function mapRole(string $role): string
    {
        return $this->roles[$role] ?? $role;
    }

    protected function toNativeContentParts(Content $content): array
    {
        if (!$content->isComposite()) {
            return [['text' => $content->toString()]];
        }

        $transformed = [];
        foreach ($content->partsList()->all() as $contentPart) {
            $transformed[] = $this->contentPartToNative($contentPart);
        }

        return $transformed;
    }

    protected function contentPartToNative(ContentPart $contentPart): array
    {
        $type = $contentPart->type();

        return match (true) {
            ($type === 'text') => $this->makeTextContentPart($contentPart),
            ($type === 'image_url') => $this->makeImageUrlContentPart($contentPart),

            default => $contentPart->toArray(),
        };
    }

    private function makeTextContentPart(ContentPart $contentPart): array
    {
        return ['text' => $contentPart->toString()];
    }

    private function makeImageUrlContentPart(ContentPart $contentPart): array
    {
        $imageUrl = $contentPart->get('image_url', []);
        $url = match (true) {
            is_array($imageUrl) => $imageUrl['url'] ?? '',
            is_string($imageUrl) => $imageUrl,
            default => '',
        };

        return [
            'inlineData' => [
                'mimeType' => Str::between($url, 'data:', ';base64,'),
                'data' => Str::after($url, ';base64,'),
            ],
        ];
    }
}
