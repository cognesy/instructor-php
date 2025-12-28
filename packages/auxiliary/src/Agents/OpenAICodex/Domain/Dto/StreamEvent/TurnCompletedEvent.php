<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Dto\StreamEvent;

use Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Value\UsageStats;

/**
 * Event emitted when a turn completes successfully
 *
 * Example: {"type":"turn.completed","usage":{"input_tokens":24763,"cached_input_tokens":24448,"output_tokens":122}}
 */
final readonly class TurnCompletedEvent extends StreamEvent
{
    public function __construct(
        public ?UsageStats $usage,
    ) {}

    public function type(): string
    {
        return 'turn.completed';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $usageData = $data['usage'] ?? null;

        return new self(
            usage: $usageData !== null ? UsageStats::fromArray($usageData) : null,
        );
    }
}
