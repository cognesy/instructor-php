<?php declare(strict_types=1);

namespace Cognesy\Agents\Continuation;

class StopSignals
{
    private array $signals;

    public function __construct(StopSignal ...$signals) {
        $this->signals = $signals;
    }

    public static function empty() : self {
        return new self();
    }

    public function withSignal(StopSignal $signal): self {
        return new self(...array_merge($this->signals, [$signal]));
    }

    // ACCESSORS /////////////////////////////////////////////////////////

    public function first() : ?StopSignal {
        return $this->signals[0] ?? null;
    }

    public function highest() : ?StopSignal {
        if ($this->signals === []) {
            return null;
        }
        return array_reduce(
            $this->signals,
            static fn(?StopSignal $carry, StopSignal $signal) => $carry === null || $signal->compare($carry) < 0
                ? $signal
                : $carry,
        );
    }

    public function hasAny() : bool {
        return $this->signals !== [];
    }

    public function toString() : string {
        $parts = [];
        foreach ($this->signals as $signal) {
            $parts[] = $signal->toString();
        }
        return implode(" | ", $parts);
    }

    // SERIALIZATION /////////////////////////////////////////////////////

    public function toArray() : array {
        $serialized = [];
        foreach ($this->signals as $signal) {
            $serialized[] = $signal->toArray();
        }
        return $serialized;
    }

    public static function fromArray(array $signals) : self {
        $instances = [];
        foreach ($signals as $signalData) {
            $instances[] = StopSignal::fromArray($signalData);
        }
        return new self(...$instances);
    }
}