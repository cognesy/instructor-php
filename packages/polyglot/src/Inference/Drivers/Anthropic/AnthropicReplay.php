<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Anthropic;

use Cognesy\Messages\ContentPart;
use Cognesy\Polyglot\Inference\Data\ReplayEnvelope;

/**
 * Validated Anthropic-private replay data aligned with semantic assistant parts.
 */
final readonly class AnthropicReplay
{
    public const string OWNER = 'anthropic';

    public static function metadataForWirePart(array $part): mixed
    {
        return match ($part['type'] ?? '') {
            'thinking' => self::thinkingMetadata($part),
            'redacted_thinking' => self::redactedMetadata($part),
            default => null,
        };
    }

    /** @return null|array{kind:string,signature?:string,data?:string} */
    public static function metadata(?ReplayEnvelope $envelope, int $position, ContentPart $semantic): ?array
    {
        $data = $envelope?->parts()[$position] ?? null;
        if (!$semantic->isReasoningPart() || !is_array($data)) {
            return null;
        }
        return match ($data['kind'] ?? '') {
            'thinking' => self::validatedThinkingMetadata($data),
            'redacted_thinking' => self::validatedRedactedMetadata($data),
            default => null,
        };
    }

    /** @return array{kind:string,signature?:string} */
    private static function thinkingMetadata(array $part): array
    {
        $metadata = ['kind' => 'thinking'];
        $signature = $part['signature'] ?? null;
        if (is_string($signature) && $signature !== '') {
            $metadata['signature'] = $signature;
        }
        return $metadata;
    }

    /** @return array{kind:string,data?:string} */
    private static function redactedMetadata(array $part): array
    {
        $metadata = ['kind' => 'redacted_thinking'];
        $data = $part['data'] ?? null;
        if (is_string($data) && $data !== '') {
            $metadata['data'] = $data;
        }
        return $metadata;
    }

    /** @return null|array{kind:string,signature?:string} */
    private static function validatedThinkingMetadata(array $data): ?array
    {
        $signature = $data['signature'] ?? null;
        if ($signature !== null && !is_string($signature)) {
            return null;
        }
        return self::thinkingMetadata($data);
    }

    /** @return null|array{kind:string,data?:string} */
    private static function validatedRedactedMetadata(array $data): ?array
    {
        $redacted = $data['data'] ?? null;
        if ($redacted !== null && !is_string($redacted)) {
            return null;
        }
        return self::redactedMetadata($data);
    }
}
