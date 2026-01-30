<?php declare(strict_types=1);

namespace Cognesy\Agents\Zero\Stop;

final readonly class ContinuationOutcome
{
    private StopSignals $signals;

    private function __construct(StopSignals $signals) {
        $this->signals = $signals;
    }

    public static function none(): self {
        return new self(StopSignals::empty());
    }

    public function withSignal(StopSignal $signal): self {
        return new self($this->signals->with($signal));
    }

    public function withReason(StopReason $reason, string $message = '', array $context = []): self {
        return $this->withSignal(new StopSignal($reason, $message, $context));
    }

    public function shouldStop(): bool {
        return !$this->shouldContinue();
    }

    public function shouldContinue(): bool {
        return match(true) {
            $this->signals->isEmptyList() => true,
            $this->signals->areAllNone() => true,
            default => false,
        };
    }

    public function stopReason(): StopSignal {
        return $this->signals->stopReason();
    }

    public function toArray(): array {
        return [
            'signals' => $this->signals->toArray(),
        ];
    }

    public static function fromArray(array $data): self {
        return new self(StopSignals::fromArray($data['signals'] ?? []));
    }
}
