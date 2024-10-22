<?php

namespace Cognesy\Instructor\Extras\Evals\Metrics\Generic;

use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Contracts\Unit;
use Cognesy\Instructor\Extras\Evals\Metrics\Traits\HandlesMetric;
use Cognesy\Instructor\Extras\Evals\Units\CountUnit;

class FractionMetric implements Metric
{
    use HandlesMetric;

    private string $name;
    private Unit $unit;

    public function __construct(
        string $name,
        private int $numerator,
        private int $denominator,
    ) {
        $this->name = $name;
        $this->unit = new CountUnit();
    }

    public function value() : float {
        return $this->numerator / $this->denominator;
    }

    public function toString(): string {
        return "{$this->numerator}/{$this->denominator}";
    }

    public function toFloat(): float {
        return $this->numerator / $this->denominator;
    }
}
