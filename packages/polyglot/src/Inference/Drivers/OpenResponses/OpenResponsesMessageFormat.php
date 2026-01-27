<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenResponses;

use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Utils\Json\Json;

/**
 * Formats messages for OpenResponses API.
 *
 * OpenResponses uses an "items" format where messages are converted to items:
 * - User messages → message items with role: "user"
 * - Assistant messages → message items with role: "assistant"
 * - Tool calls → function_call items
 * - Tool results → function_call_output items
 *
 * However, OpenResponses also accepts the standard messages format as input,
 * so we can pass through with minimal transformation.
 */
class OpenResponsesMessageFormat implements CanMapMessages
{
    #[\Override]
    public function map(array $messages): array {
        $list = [];
        foreach ($messages as $message) {
            $nativeMessage = $this->mapMessage($message);
            if (empty($nativeMessage)) {
                continue;
            }
            // mapMessage may return multiple items (e.g., for tool calls)
            if (isset($nativeMessage[0]) && is_array($nativeMessage[0])) {
                foreach ($nativeMessage as $item) {
                    $list[] = $item;
                }
            } else {
                $list[] = $nativeMessage;
            }
        }
        return $list;
    }

    // INTERNAL /////////////////////////////////////////////

    protected function mapMessage(array $message): array {
        $role = $message['role'] ?? '';
        $hasToolCalls = !empty($message['_metadata']['tool_calls'] ?? []);

        return match(true) {
            $role === 'assistant' && $hasToolCalls => $this->toFunctionCallItems($message),
            $role === 'tool' => $this->toFunctionCallOutputItem($message),
            default => $this->toMessageItem($message),
        };
    }

    /**
     * Convert a regular message to a message item.
     */
    protected function toMessageItem(array $message): array {
        $role = $message['role'] ?? 'user';
        $content = $message['content'] ?? '';

        // Convert content to the appropriate format
        $contentItems = $this->toContentItems($content);

        return [
            'type' => 'message',
            'role' => $role,
            'content' => $contentItems,
        ];
    }

    /**
     * Convert assistant message with tool calls to function_call items.
     * Returns an array of items (message content + function calls).
     */
    protected function toFunctionCallItems(array $message): array {
        $items = [];
        $toolCalls = $message['_metadata']['tool_calls'] ?? [];

        // If there's also text content, add it as a message item first
        $content = $message['content'] ?? '';
        if (!empty($content)) {
            $items[] = [
                'type' => 'message',
                'role' => 'assistant',
                'content' => $this->toContentItems($content),
            ];
        }

        // Add function_call items for each tool call
        foreach ($toolCalls as $toolCall) {
            $items[] = [
                'type' => 'function_call',
                'call_id' => $toolCall['id'] ?? '',
                'name' => $toolCall['function']['name'] ?? '',
                'arguments' => $toolCall['function']['arguments'] ?? '{}',
            ];
        }

        return $items;
    }

    /**
     * Convert a tool result message to function_call_output item.
     */
    protected function toFunctionCallOutputItem(array $message): array {
        $callId = $message['_metadata']['tool_call_id'] ?? '';
        $content = $message['content'] ?? '';

        // Ensure content is a string
        if (is_array($content)) {
            $content = Json::encode($content);
        }

        return [
            'type' => 'function_call_output',
            'call_id' => $callId,
            'output' => $content,
        ];
    }

    /**
     * Convert content to content items array.
     */
    protected function toContentItems(string|array $content): array {
        if (is_string($content)) {
            return [[
                'type' => 'input_text',
                'text' => $content,
            ]];
        }

        // Content is already an array of parts
        $items = [];
        foreach ($content as $part) {
            if (is_string($part)) {
                $items[] = [
                    'type' => 'input_text',
                    'text' => $part,
                ];
            } elseif (isset($part['type'])) {
                // Handle different content types
                $items[] = match($part['type']) {
                    'text' => [
                        'type' => 'input_text',
                        'text' => $part['text'] ?? '',
                    ],
                    'image_url' => [
                        'type' => 'input_image',
                        'image_url' => $part['image_url'] ?? '',
                    ],
                    default => $part, // Pass through unknown types
                };
            }
        }
        return $items;
    }
}
