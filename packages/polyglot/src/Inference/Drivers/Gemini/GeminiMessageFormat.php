<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Gemini;

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCall;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Contracts\MessageMapper;
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
            $message->isAssistant() && $message->hasToolCalls() => $this->toNativeToolCall($message),
            $message->isTool() && $message->hasToolResult() => $this->toNativeToolResult($message),
            default => $this->toNativeTextMessage($message),
        };
    }

    private function toNativeTextMessage(Message $message): array
    {
        return [
            'role' => $this->mapRole($message->role()->value),
            'parts' => $this->toNativeContentParts($message->content()),
        ];
    }

    private function toNativeToolCall(Message $message): array
    {
        return [
            'role' => 'model',
            'parts' => $message->toolCalls()->map(
                fn (ToolCall $tc) => [
                    'functionCall' => [
                        'name' => $tc->name(),
                        'args' => $tc->arguments(),
                    ],
                ],
            ),
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
                        'content' => $message->content()->toString(),
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
