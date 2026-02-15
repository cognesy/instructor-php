<?php declare(strict_types=1);

namespace Cognesy\Agents\Continuation;

class ExecutionContinuation
{
    private StopSignals $stopSignals;
    private bool $isContinuationRequested;

    public function __construct(
        StopSignals $stopSignals,
        bool $isContinuationRequested = false,
    ) {
        $this->stopSignals = $stopSignals;
        $this->isContinuationRequested = $isContinuationRequested;
    }

    public static function fresh(): self {
        return new self(
            stopSignals: StopSignals::empty(),
            isContinuationRequested: false,
        );
    }

    public function shouldStop(): bool {
        return $this->stopSignals->hasAny()
            && !$this->isContinuationRequested;
    }

    public function stopSignals(): StopSignals {
        return $this->stopSignals;
    }

    public function isContinuationRequested(): bool {
        return $this->isContinuationRequested;
    }

    public function withContinuationRequested(bool $value): self {
        return new self(
            stopSignals: $this->stopSignals,
            isContinuationRequested: $value,
        );
    }

    public function withStopSignals(StopSignals $signals): self {
        return new self(
            stopSignals: $signals,
            isContinuationRequested: $this->isContinuationRequested,
        );
    }

    public function withNewStopSignal(StopSignal $signal): self {
        return new self(
            stopSignals: $this->stopSignals->withSignal($signal),
            isContinuationRequested: $this->isContinuationRequested,
        );
    }

    public function toArray(): array {
        return [
            'stop_signals' => $this->stopSignals->toArray(),
            'is_continuation_requested' => $this->isContinuationRequested,
        ];
    }

    public static function fromArray(array $data): self {
        $stopSignalsData = $data['stop_signals'] ?? [];
        $isContinuationRequested = $data['is_continuation_requested'] ?? false;
        return new self(
            stopSignals: StopSignals::fromArray($stopSignalsData),
            isContinuationRequested: $isContinuationRequested,
        );
    }

    public function explain() : string {
        $parts = [];
        if ($this->stopSignals->hasAny()) {
            $parts[] = "Stop Signals: " . $this->stopSignals->toString();
        } else {
            $parts[] = "No Stop Signals";
        }
        $parts[] = "Continuation Requested: " . ($this->isContinuationRequested ? 'Yes' : 'No');
        return implode("; ", $parts);
    }
}