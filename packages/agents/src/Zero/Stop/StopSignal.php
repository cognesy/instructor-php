<?php declare(strict_types=1);

namespace Cognesy\Agents\Zero\Stop;

final readonly class StopSignal
{
    public function __construct(
        public StopReason $reason,
        public string     $message = '',
        public array      $context = [],
    ) {}

    public static function none(): StopSignal {
        return new self(StopReason::None);
    }

    public function isNone(): bool {
        return $this->reason === StopReason::None;
    }

    public function toArray(): array {
        return [
            'reason' => $this->reason->value,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }

    public static function fromArray(array $data): StopSignal {
        return new self(
            reason: StopReason::from($data['reason'] ?? 'none'),
            message: $data['message'] ?? '',
            context: $data['context'] ?? [],
        );
    }

    /**
     * Compare this StopSignal against another for sorting.
     * Returns -1, 0, 1 like spaceship operator based on configured priority.
     */
    public function compare(StopSignal $other): int {
        return $this->reason->compare($other->reason);
    }
}
