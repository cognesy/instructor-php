<?php

namespace Cognesy\Instructor\Extras\Evals\Observers;

use Cognesy\Instructor\Extras\Evals\Contracts\Observer;
use Cognesy\Instructor\Extras\Evals\Observation;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class UsageObserver implements Observer
{
    private Usage $usage;
    private Observation $observation;

    public function __construct(
        private string $key,
        private array $metadata = []
    ) {
        $this->observation = Observation::make(
            type: 'metric',
            key: $this->key,
            value: 0,
            metadata: $this->metadata,
        );
        $this->usage = new Usage();
    }

    public static function start(string $key): static {
        return new UsageObserver($key);
    }

    public function reset(): static {
        $this->usage = new Usage();
        return $this;
    }

    public function with(array $metadata = []): static {
        $this->observation = $this->observation->withMetadata($metadata);
        return $this;
    }

    public function make(mixed $value = null): Observation {
        $usage = match(true) {
            is_array($value) => Usage::fromArray($value),
            is_object($value) && $value instanceof Usage => $value,
            is_null($value) => throw new \InvalidArgumentException('You must provide a usage data'),
            default => throw new \InvalidArgumentException('Invalid value type'),
        };
        $details = match(true) {
            is_array($value) => $value,
            is_object($value) && $value instanceof Usage => $value->toArray(),
            default => throw new \InvalidArgumentException('Invalid value type'),
        };
        $this->usage->accumulate($usage);
        return $this->observation
            ->withValue($this->usage->total())
            ->withMetadata(['tokens' => $details]);
    }
}
