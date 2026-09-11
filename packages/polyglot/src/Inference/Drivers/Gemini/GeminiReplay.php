<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Gemini;

use Cognesy\Messages\ContentPart;
use Cognesy\Polyglot\Inference\Data\ReplayEnvelope;

/**
 * Validated Gemini-private replay data aligned with semantic assistant parts.
 */
final readonly class GeminiReplay
{
    public const string OWNER = 'gemini';

    public static function metadataForWirePart(array $part): mixed
    {
        $signature = $part['thoughtSignature'] ?? $part['thought_signature'] ?? null;
        return match (true) {
            is_string($signature) && $signature !== '' => ['thoughtSignature' => $signature],
            default => null,
        };
    }

    /** @return null|array{thoughtSignature:string} */
    public static function metadata(?ReplayEnvelope $envelope, int $position, ContentPart $semantic): ?array
    {
        $data = $envelope?->parts()[$position] ?? null;
        $signature = match (true) {
            is_array($data) => $data['thoughtSignature'] ?? null,
            default => null,
        };
        if (!is_string($signature) || $signature === '') {
            return null;
        }
        if (!$semantic->isTextPart() && !$semantic->isReasoningPart() && !$semantic->isToolCallPart()) {
            return null;
        }
        return ['thoughtSignature' => $signature];
    }
}
