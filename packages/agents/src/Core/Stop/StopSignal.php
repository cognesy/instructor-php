<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Stop;

final readonly class StopSignal
{
    /**
     * @param array<string, mixed> $context
     * @param class-string|null $source
     */
    public function __construct(
        public StopReason $reason,
        public string $message = '',
        public array $context = [],
        public ?string $source = null,
    ) {}

    public static function fromStopException(AgentStopException $stop) : self {
        return new self(
            reason: StopReason::StopRequested,
            message: $stop->getMessage(),
            context: $stop->context,
            source: $stop->source,
        );
    }

    // ACCESSORS /////////////////////////////////////////////////////////

    public function toString(): string {
        return $this->reason->value . ($this->message !== '' ? ": {$this->message}" : '');
    }

    // COMPARISON /////////////////////////////////////////////////////////

    public function compare(self $other): int {
        return $this->reason->compare($other->reason);
    }

    // SERIALIZATION ///////////////////////////////////////////////////////

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'reason' => $this->reason->value,
            'message' => $this->message,
            'context' => $this->context,
            'source' => $this->source,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self {
        $reason = $data['reason'] ?? StopReason::Completed->value;
        $source = $data['source'] ?? null;
        $sourceValue = is_string($source) && $source !== '' ? $source : null;

        return new self(
            reason: StopReason::from($reason),
            message: $data['message'] ?? '',
            context: is_array($data['context'] ?? null) ? $data['context'] : [],
            source: $sourceValue,
        );
    }
}
