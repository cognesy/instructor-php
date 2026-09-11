<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Anthropic;

final class AnthropicCache
{
    public static function markBlocks(array $blocks, ?string $ttl): array
    {
        for ($index = count($blocks) - 1; $index >= 0; --$index) {
            if (!self::eligible($blocks[$index])) {
                continue;
            }
            $blocks[$index]['cache_control'] ??= self::control($ttl);
            return $blocks;
        }
        return $blocks;
    }

    public static function markMessages(array $messages, ?string $ttl): array
    {
        for ($index = count($messages) - 1; $index >= 0; --$index) {
            $content = $messages[$index]['content'] ?? [];
            $blocks = match (true) {
                is_string($content) => [['type' => 'text', 'text' => $content]],
                default => $content,
            };
            $marked = self::markBlocks($blocks, $ttl);
            if (!self::hasBreakpoint($marked)) {
                continue;
            }
            $messages[$index]['content'] = $marked;
            return $messages;
        }
        return $messages;
    }

    public static function control(?string $ttl): array
    {
        return array_filter(['type' => 'ephemeral', 'ttl' => $ttl], static fn(mixed $value): bool => $value !== null);
    }

    private static function eligible(array $block): bool
    {
        return match ($block['type'] ?? '') {
            'text' => trim($block['text'] ?? '') !== '',
            'image', 'document', 'tool_use', 'tool_result' => true,
            default => false,
        };
    }

    private static function hasBreakpoint(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (isset($block['cache_control']) && self::eligible($block)) {
                return true;
            }
        }
        return false;
    }
}
