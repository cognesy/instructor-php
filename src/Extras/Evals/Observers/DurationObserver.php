<?php

namespace Cognesy\Instructor\Extras\Evals\Observers;

use Cognesy\Instructor\Extras\Evals\Contracts\Observer;
use Cognesy\Instructor\Extras\Evals\Observation;
use DateTimeImmutable;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class DurationObserver implements Observer
{
    private DateTimeImmutable $start;
    private Observation $observation;

    public function __construct(
        private string $key,
        private array $metadata = [],
    ) {
        $this->start = new DateTimeImmutable();
        $this->observation = Observation::make(
            type: 'metric',
            key: $this->key,
            value: null,
            metadata: $this->metadata,
        );
    }

    public static function start(string $key): static {
        return new DurationObserver($key);
    }

    public function reset(): static {
        $this->start = new DateTimeImmutable();
        return $this;
    }

    public function with(array $metadata = []): static {
        $this->observation = $this->observation->withMetadata($metadata);
        return $this;
    }

    public function make(mixed $value = null) : Observation {
        $seconds = match(true) {
            is_int($value) => $value,
            is_float($value) => $value,
            is_string($value) => strtotime($value),
            is_null($value) => null,
            default => throw new \InvalidArgumentException('Invalid value type'),
        };
        $secondsElapsed = $seconds ?? $this->diff($this->start, new DateTimeImmutable());
        return $this->observation->withValue($secondsElapsed);
    }

    // INTERNAL ////////////////////////////////////////

    private function diff(DateTimeImmutable $start, DateTimeImmutable $end) : float {
        $startWithMicroseconds = $start->getTimestamp() + $start->format('u') / 1_000_000;
        $endWithMicroseconds = $end->getTimestamp() + $end->format('u') / 1_000_000;
        return abs($endWithMicroseconds - $startWithMicroseconds);
    }
}
