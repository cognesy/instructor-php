<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Assembly;

use Cognesy\Messages\ContentPart;
use Cognesy\Messages\ContentParts;
use Cognesy\Polyglot\Inference\Data\ReplayEnvelope;
use InvalidArgumentException;

/**
 * Positionally aligned semantic and provider replay parts from one response parse.
 */
final readonly class AssistantMessageParseResult
{
    private function __construct(
        private ContentParts $parts,
        private ?ReplayEnvelope $replay,
    ) {}

    /**
     * @param list<ContentPart> $parts
     * @param list<mixed> $replayParts
     */
    public static function fromParts(
        string $owner,
        array $parts,
        array $replayParts,
        mixed $response = null,
    ): self {
        if (count($parts) !== count($replayParts)) {
            throw new InvalidArgumentException('Semantic and replay parts must be positionally aligned.');
        }
        return new self(
            parts: new ContentParts(...$parts),
            replay: ReplayEnvelope::fromParts($owner, $replayParts, $response),
        );
    }

    public function parts(): ContentParts
    {
        return $this->parts;
    }

    public function replay(): ?ReplayEnvelope
    {
        return $this->replay;
    }
}
