<?php

namespace Cognesy\Instructor\Extras\Evals\Metrics\Aggregates;

use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Metrics\Traits\HandlesMetric;
use Cognesy\Instructor\Extras\Evals\Units\PercentageUnit;
use Cognesy\Instructor\Utils\Cli\Color;

class Reliability implements Metric
{
    use HandlesMetric;

    public function __construct(
        string $name,
        float  $value,
    )
    {
        $this->name = $name;
        $this->unit = new PercentageUnit();
        if (!$this->unit->isValid($value)) {
            throw new \InvalidArgumentException("Invalid value for reliability metric: $value");
        }
        $this->value = $value;
    }

    public function toCliColor(): array
    {
        return match (true) {
            $this->value < 0.5 => [Color::BG_RED, Color::WHITE],
            $this->value < 0.8 => [Color::BG_YELLOW, Color::BLACK],
            default => [Color::BG_GREEN, Color::WHITE],
        };
    }
}
