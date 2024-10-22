<?php

namespace Cognesy\Instructor\Extras\Evals\Metrics\Aggregates;

use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Metrics\Traits\HandlesMetric;
use Cognesy\Instructor\Extras\Evals\Units\DurationUnit;
use Cognesy\Instructor\Utils\Cli\Color;

class Latency implements Metric
{
    use HandlesMetric;

    private int $percentile;

    public function __construct(
        string $name,
        float $value,
        int $percentile,
    ) {
        $this->name = $name;
        $this->unit = new DurationUnit();
        $this->percentile = $percentile;
        if (!$this->unit->isValid($value)) {
            throw new \InvalidArgumentException("Invalid value for latency metric: $value");
        }
        $this->value = $value;
    }

    public function toString(array $format = []): string {
        return $this->unit->toString($this->value, $format) . " (P{$this->percentile})";
    }

    public function toCliColor(): array
    {
        // Assuming latency is in milliseconds, adjust thresholds as needed
        return match(true) {
            $this->value > 3000 => [Color::BG_RED, Color::WHITE],
            $this->value > 1000 => [Color::BG_YELLOW, Color::BLACK],
            default => [Color::BG_GREEN, Color::WHITE],
        };
    }
}
