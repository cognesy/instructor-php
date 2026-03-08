<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Fallback event for unrecognized event types
 */
final readonly class UnknownEvent extends StreamEvent
{
    /**
     * @param array<string, mixed> $rawData
     */
    public function __construct(
        public string $rawType,
        public array $rawData,
    ) {}

    public function type(): string
    {
        return $this->rawType;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            rawType: Normalize::toString($data['type'] ?? 'unknown', 'unknown'),
            rawData: $data,
        );
    }
}
