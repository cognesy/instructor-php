<?php

namespace Cognesy\Instructor\Extras\Evals\Metrics\Aggregates;

use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Metrics\Traits\HandlesMetric;
use Cognesy\Instructor\Extras\Evals\Units\DurationUnit;
use Cognesy\Instructor\Utils\Cli\Color;

class LatencyP95 implements Metric
{
    use HandlesMetric;

    public function __construct(
        string $name,
        float $value,
    ) {
        $this->name = $name;
        $this->unit = new DurationUnit();
        if (!$this->unit->isValid($value)) {
            throw new \InvalidArgumentException("Invalid value for latency P95 metric: $value");
        }
        $this->value = $value;
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
