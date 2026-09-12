<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Fallback event for unrecognized event types
 */
final readonly class UnknownEvent extends StreamEvent
{
    /** @param array<string, mixed> $rawData */
    public function __construct(
        array $rawData,
        public string $rawType,
    ) {
        parent::__construct($rawData);
    }

    #[\Override]
    public function type(): string
    {
        return $this->rawType;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            rawData: $data,
            rawType: Normalize::toString($data['type'] ?? 'unknown', 'unknown'),
        );
    }
}
