<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Event emitted when a turn finishes
 *
 * Contains the assistant message and any tool results.
 *
 * Example: {"type":"turn_end","message":{...},"toolResults":[]}
 */
final readonly class TurnEndEvent extends StreamEvent
{
    /**
     * @param array<array-key, mixed> $rawData
     * @param array<array-key, mixed> $message
     * @param array<array-key, mixed> $toolResults
     */
    public function __construct(
        array $rawData,
        public array $message,
        public array $toolResults,
    ) {
        parent::__construct($rawData);
    }

    #[\Override]
    public function type(): string
    {
        return 'turn_end';
    }

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            rawData: $data,
            message: Normalize::toArray($data['message'] ?? []),
            toolResults: Normalize::toArray($data['toolResults'] ?? []),
        );
    }
}
