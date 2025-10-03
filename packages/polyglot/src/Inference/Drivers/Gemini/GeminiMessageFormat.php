<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Gemini;

use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Utils\Json\Json;
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

    private function mapMessage(array $message) : array {
        return match(true) {
            ($message['role'] ?? '') === 'assistant' && !empty($message['_metadata']['tool_calls'] ?? []) => $this->toNativeToolCall($message),
            ($message['role'] ?? '') === 'tool' => $this->toNativeToolResult($message),
            default => $this->toNativeTextMessage($message),
        };
    }

    private function toNativeTextMessage(array $message) : array {
        return [
            'role' => $this->mapRole($message['role'] ?? 'user'),
            'parts' => $this->toNativeContentParts($message['content']),
        ];
    }

    private function toNativeToolCall(array $message) : array {
        return [
            'role' => 'model',
            'parts' => array_map(
                callback: fn($call) => $this->toNativeToolCallPart($call),
                array: $message['_metadata']['tool_calls'] ?? []
            ),
        ];
    }

    private function toNativeToolCallPart(array $call) : array {
        $arguments = $call['function']['arguments'] ?? [];
        return [
            'functionCall' => [
                'name' => $call['function']['name'] ?? '',
                'args' => is_string($arguments) ? Json::fromString($arguments)->toArray() : $arguments,
            ]
        ];
    }

    private function toNativeToolResult(array $message) : array {
        $result = $message['_metadata']['result'] ?? '';
        $content = match(true) {
            is_array($result) => $result,
            is_string($result) => Json::fromString($result)->toArray(),
            default => $message['content'],
        };
        return [
            'role' => 'user',
            'parts' => [[
                'functionResponse' => [
                    'name' => $message['_metadata']['tool_name'] ?? '',
                    'response' => [
                        'name' => $message['_metadata']['tool_name'] ?? '',
                        'content' => $content,
                    ],
                ],
            ]],
        ];
    }

    protected function mapRole(string $role) : string {
        return $this->roles[$role] ?? $role;
    }

    protected function toNativeContentParts(string|array $contentParts) : array {
        if (is_string($contentParts)) {
            return [["text" => $contentParts]];
        }
        $transformed = [];
        foreach ($contentParts as $contentPart) {
            $transformed[] = $this->contentPartToNative($contentPart);
        }
        return $transformed;
    }

    protected function contentPartToNative(array $contentPart) : array {
        $type = $contentPart['type'] ?? 'text';
        return match(true) {
            ($type === 'text') => $this->makeTextContentPart($contentPart),
            ($type === 'image_url') => $this->makeImageUrlContentPart($contentPart),

            default => $contentPart,
        };
    }

    private function makeTextContentPart(array $contentPart) : array {
        return ['text' => $contentPart['text'] ?? ''];
    }

    private function makeImageUrlContentPart(array $contentPart) : array {
        return [
            'inlineData' => [
                'mimeType' => Str::between($contentPart['image_url']['url'], 'data:', ';base64,'),
                'data' => Str::after($contentPart['image_url']['url'], ';base64,'),
            ],
        ];
    }
}