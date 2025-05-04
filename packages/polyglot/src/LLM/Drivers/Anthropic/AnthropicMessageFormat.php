<?php

namespace Cognesy\Polyglot\LLM\Drivers\Anthropic;

use Cognesy\Polyglot\LLM\Contracts\CanMapMessages;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\Str;

class AnthropicMessageFormat implements CanMapMessages
{
    private $roles = [
        'user' => 'user',
        'assistant' => 'assistant',
        'system' => 'user',
        'developer' => 'user',
        'tool' => 'user',
    ];

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

    // INTERNAL /////////////////////////////////////////////

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
            'content' => $this->toNativeContent($message['content']),
        ];
    }

    private function mapRole(string $role) : string {
        return $this->roles[$role] ?? $role;
    }

    private function toNativeContent(string|array $content) : string|array {
        if (is_string($content)) {
            return $content;
        }
        // if content is array - process each part
        $transformed = [];
        foreach ($content as $contentPart) {
            $transformed[] = $this->contentPartToNative($contentPart);
        }
        return $transformed;
    }

    private function contentPartToNative(array $contentPart) : array {
        $type = $contentPart['type'] ?? 'text';
        return match($type) {
            'text' => $this->toNativeTextContent($contentPart),
            'image_url' => $this->toNativeImage($contentPart),
            default => $contentPart,
        };
    }

    private function toNativeTextContent(array $contentPart) : array {
        return [
            'type' => 'text',
            'text' => $contentPart['text'] ?? '',
        ];
    }

    private function toNativeImage(array $contentPart) : array {
        $mimeType = Str::between($contentPart['image_url']['url'], 'data:', ';base64,');
        $base64content = Str::after($contentPart['image_url']['url'], ';base64,');
        $contentPart = [
            'type' => 'image',
            'source' => [
                'type' => 'base64',
                'media_type' => $mimeType,
                'data' => $base64content,
            ],
        ];
        return $contentPart;
    }

    private function toNativeToolCall(array $message) : array {
        return [
            'role' => 'assistant',
            'content' => [[
                'type' => 'tool_use',
                'id' => $message['_metadata']['tool_calls'][0]['id'] ?? '',
                'name' => $message['_metadata']['tool_calls'][0]['function']['name'] ?? '',
                'input' => Json::fromString($message['_metadata']['tool_calls'][0]['function']['arguments'] ?? '')->toArray(),
            ]]
        ];
    }

    private function toNativeToolResult(array $message) : array {
        return [
            'role' => 'user',
            'content' => [[
                'type' => 'tool_result',
                'tool_use_id' => $message['_metadata']['tool_call_id'] ?? '',
                'content' => $message['content'] ?? '',
                //'is_error' => false,
            ]]
        ];
    }

    private function setCacheMarker(array $messages): array {
        $lastIndex = count($messages) - 1;
        $lastMessage = $messages[$lastIndex];

        if (is_array($lastMessage['content'])) {
            $subIndex = count($lastMessage['content']) - 1;
            $lastMessage['content'][$subIndex]['cache_control'] = ["type" => "ephemeral"];
        } else {
            $lastMessage['content'] = [[
                'type' => $lastMessage['type'] ?? 'text',
                'text' => $lastMessage['content'] ?? '',
                'cache_control' => ["type" => "ephemeral"],
            ]];
        }

        $messages[$lastIndex] = $lastMessage;
        return $messages;
    }
}