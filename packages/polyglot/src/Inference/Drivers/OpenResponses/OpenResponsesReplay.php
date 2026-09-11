<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenResponses;

use Cognesy\Messages\ContentPart;
use Cognesy\Polyglot\Inference\Data\ReplayEnvelope;

/**
 * Validated OpenAI Responses replay data aligned with semantic assistant parts.
 */
final readonly class OpenResponsesReplay
{
    public const string OWNER = 'openresponses';

    /** @param array<string,mixed> $item */
    public static function metadataForReasoningItem(array $item): mixed
    {
        if (($item['type'] ?? '') !== 'reasoning') {
            return null;
        }
        $allowed = array_fill_keys(
            ['type', 'id', 'status', 'phase', 'summary', 'content', 'encrypted_content'],
            true,
        );
        $replayable = [];
        foreach ($item as $field => $value) {
            if (isset($allowed[$field])) {
                $replayable[$field] = $value;
            }
        }
        return match (true) {
            count($replayable) > 1 => ['item' => $replayable],
            default => null,
        };
    }

    /** @return null|array<string,mixed> */
    public static function reasoningItem(?ReplayEnvelope $envelope, int $position, ContentPart $semantic): ?array
    {
        if (!$semantic->isReasoningPart()) {
            return null;
        }
        $metadata = $envelope?->parts()[$position] ?? null;
        $item = match (true) {
            is_array($metadata) && is_array($metadata['item'] ?? null) => $metadata['item'],
            default => null,
        };
        if (!is_array($item) || !self::isValidReasoningItem($item)) {
            return null;
        }
        if (self::reasoningText($item) !== $semantic->reasoningText()) {
            return null;
        }
        return $item;
    }

    /** @param array<string,mixed> $item */
    public static function reasoningText(array $item): string
    {
        $text = '';
        foreach (['content', 'summary'] as $field) {
            $parts = $item[$field] ?? [];
            if (!is_array($parts)) {
                continue;
            }
            foreach ($parts as $part) {
                if (is_array($part) && is_string($part['text'] ?? null)) {
                    $text .= $part['text'];
                }
            }
        }
        return $text;
    }

    /** @param array<string,mixed> $item */
    private static function isValidReasoningItem(array $item): bool
    {
        if (($item['type'] ?? '') !== 'reasoning') {
            return false;
        }
        foreach (['id', 'status', 'phase', 'encrypted_content'] as $field) {
            if (isset($item[$field]) && !is_string($item[$field])) {
                return false;
            }
        }
        foreach (['summary', 'content'] as $field) {
            if (isset($item[$field]) && !is_array($item[$field])) {
                return false;
            }
        }
        return isset($item['id']) || isset($item['encrypted_content']);
    }
}
